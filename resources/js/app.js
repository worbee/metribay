import '../css/app.css';
import { Chart, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler);

const root = document.documentElement;
const charts = new Set();
const storage = {
    get(key, fallback) { try { return localStorage.getItem(`metribay.${key}`) || fallback; } catch { return fallback; } },
    set(key, value) { try { localStorage.setItem(`metribay.${key}`, value); } catch { /* Preferences remain usable for this page when storage is unavailable. */ } },
};
const media = window.matchMedia('(prefers-color-scheme: dark)');
let scheduled = false;
let i18n = {};
try { i18n = JSON.parse(document.getElementById('metribay-i18n')?.textContent || '{}'); } catch { /* English strings remain. */ }
// Translates interface text supplied by the server, keyed by its English source; :name placeholders are replaced.
function t(text, values = {}) {
    let output = i18n[text] ?? text;
    for (const [name, value] of Object.entries(values)) output = output.replaceAll(`:${name}`, value);
    return output;
}

function themePreference() {
    const value = storage.get('theme', 'system');
    return ['light', 'dark', 'system'].includes(value) ? value : 'system';
}
function applyTheme(preference = themePreference(), save = false) {
    if (!['light', 'dark', 'system'].includes(preference)) return;
    if (save) storage.set('theme', preference);
    root.dataset.theme = preference === 'system' ? (media.matches ? 'dark' : 'light') : preference;
    document.querySelectorAll('[data-theme-input]').forEach(input => { input.checked = input.value === preference; });
    document.querySelectorAll('[data-theme-cycle]').forEach(button => {
        const next = preference === 'system' ? (root.dataset.theme === 'light' ? 'dark' : 'light') : { light: 'dark', dark: 'system' }[preference];
        const names = { light: t('Light'), dark: t('Dark'), system: t('System') };
        const label = t('Theme: :current. Switch to :next.', { current: names[preference], next: names[next] });
        button.title = label;
        button.setAttribute('aria-label', label);
        button.dataset.currentTheme = preference;
    });
    queueSync();
}
media.addEventListener('change', () => { if (themePreference() === 'system') applyTheme(); });

function finite(value) { return value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) ? Number(value) : null; }
function formatNumber(value, unit = 'count', compact = false) {
    if (value === null) return t('Unavailable');
    if (unit === 'status') return ({ 0: t('Paused'), 1: t('Not checked yet'), 2: t('Up'), 8: t('Seems down'), 9: t('Down') })[value] ?? String(value);
    const options = { maximumFractionDigits: unit === 'score' ? 0 : (unit === 'ratio' ? 3 : (unit === 'percent' ? 2 : (Number.isInteger(value) ? 0 : 1))) };
    if (compact && Math.abs(value) >= 1000 && !['percent', 'ms', 'score', 'ratio'].includes(unit)) {
        options.notation = 'compact';
        options.maximumFractionDigits = 1;
    }
    const formatted = new Intl.NumberFormat(document.documentElement.lang || 'en', options).format(value);
    return unit === 'percent' ? `${formatted}%` : unit === 'ms' ? `${formatted} ms` : unit === 'score' ? `${formatted}/100` : formatted;
}
function formatDate(value, full = false) {
    const date = new Date(`${value}T12:00:00Z`);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat(document.documentElement.lang || 'en', { day: 'numeric', month: 'short', ...(full ? { year: 'numeric' } : {}), timeZone: 'UTC' }).format(date);
}
function syncNumbers() {
    const compact = storage.get('numbers', 'full') === 'compact';
    document.querySelectorAll('[data-metric-number]').forEach(element => {
        const value = finite(element.dataset.metricNumber);
        if (value === null) return;
        const formatted = formatNumber(value, element.dataset.unit, compact);
        if (element.textContent !== formatted) element.textContent = formatted;
        element.title = formatNumber(value, element.dataset.unit, false);
    });
}
function chartColors() {
    const style = getComputedStyle(root);
    return Object.fromEntries(['chart-line', 'chart-previous', 'chart-grid', 'text-muted', 'page', 'text', 'event-blue', 'event-green', 'event-amber', 'event-violet', 'event-automatic'].map(name => [name, style.getPropertyValue(`--${name}`).trim()]));
}
// Draws project events as vertical markers: dots for custom events, diamonds for imported ones.
const eventMarkers = {
    id: 'metribayEvents',
    afterDatasetsDraw(chart, _args, options) {
        const events = options.events ?? [];
        if (!events.length) return;
        const { ctx, chartArea: { top, bottom }, scales: { x } } = chart;
        ctx.save();
        for (const event of events) {
            const index = options.labels.indexOf(event.date);
            if (index < 0) continue;
            const position = x.getPixelForValue(index);
            const color = options.colors[`event-${event.automatic ? 'automatic' : event.color}`] || options.colors['event-blue'];
            ctx.strokeStyle = color;
            ctx.fillStyle = color;
            ctx.lineWidth = 1.4;
            ctx.setLineDash(event.automatic ? [2, 3] : [4, 3]);
            ctx.beginPath();
            ctx.moveTo(position, top + 6);
            ctx.lineTo(position, bottom);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.beginPath();
            if (event.automatic) {
                ctx.moveTo(position, top - 1);
                ctx.lineTo(position + 4, top + 3);
                ctx.lineTo(position, top + 7);
                ctx.lineTo(position - 4, top + 3);
                ctx.closePath();
            } else {
                ctx.arc(position, top + 3, 3.5, 0, Math.PI * 2);
            }
            ctx.fill();
        }
        ctx.restore();
    },
};
function makeChart(canvas, config, signature) {
    const colors = chartColors();
    const compact = Boolean(config.compact);
    const series = Array.isArray(config.series) ? config.series : [];
    const previous = Array.isArray(config.previous) ? config.previous : [];
    const labels = series.map(point => point.date);
    const hasPrevious = previous.some(point => finite(point.value) !== null);
    const events = compact || !Array.isArray(config.events) ? [] : config.events.filter(event => labels.includes(event.date));
    const eventsOn = date => events.filter(event => event.date === date);
    const datasets = [{
        label: t('Selected period'),
        data: series.map(point => finite(point.value)),
        borderColor: colors['chart-line'],
        backgroundColor: `${colors['chart-line']}0c`,
        borderWidth: compact ? 1.7 : 2,
        pointRadius: compact ? 0 : 1.7,
        pointHoverRadius: compact ? 3 : 4,
        pointBorderWidth: 1.5,
        pointBackgroundColor(context) { return series[context.dataIndex]?.partial ? colors.page : colors['chart-line']; },
        pointBorderColor: colors['chart-line'],
        spanGaps: false,
        tension: 0.18,
        fill: true,
        stepped: config.unit === 'status',
    }];
    if (hasPrevious) datasets.push({
        label: t('Previous period'),
        data: labels.map((_, index) => finite(previous[index]?.value)),
        borderColor: colors['chart-previous'],
        backgroundColor: 'transparent',
        borderDash: [3, 4],
        borderWidth: 1.2,
        pointRadius: 0,
        pointHoverRadius: compact ? 2 : 3,
        spanGaps: false,
        tension: 0.18,
        fill: false,
        stepped: config.unit === 'status',
    });
    for (const [index, dataset] of datasets.entries()) {
        const toggle = canvas.closest('.metric')?.querySelector(`[data-chart-toggle="${index}"]`);
        dataset.hidden = toggle?.getAttribute('aria-pressed') === 'false';
    }
    const chart = new Chart(canvas, {
        type: 'line',
        data: { labels, datasets },
        plugins: [eventMarkers],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            normalized: true,
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { top: events.length ? 10 : 4, left: compact ? 0 : 2, right: 2, bottom: 0 } },
            plugins: {
                legend: { display: false },
                metribayEvents: { events, labels, colors },
                tooltip: {
                    enabled: true,
                    backgroundColor: colors.text,
                    titleColor: colors.page,
                    bodyColor: colors.page,
                    padding: 11,
                    displayColors: !compact,
                    titleFont: { family: 'system-ui', size: 11, weight: 'normal' },
                    bodyFont: { family: 'system-ui', size: 11 },
                    callbacks: {
                        title(items) { return items.length ? formatDate(series[items[0].dataIndex]?.date ?? '', true) : ''; },
                        label(context) {
                            const point = (context.datasetIndex === 0 ? series : previous)[context.dataIndex];
                            const value = formatNumber(finite(context.raw), config.unit);
                            const label = context.datasetIndex === 0 ? config.label : t('Previous (:date)', { date: formatDate(point?.date ?? '') });
                            return `${label}: ${value}${point?.partial ? ` · ${t('partial')}` : ''}`;
                        },
                        afterBody(items) {
                            if (!items.length) return [];
                            const point = (items[0].datasetIndex === 0 ? series : previous)[items[0].dataIndex];
                            const window = point?.context?.collection_start ? [`${point.context.collection_start} – ${point.context.collection_end}`] : [];
                            return [...window, ...eventsOn(series[items[0].dataIndex]?.date).map(event => `${event.automatic ? '◆' : '●'} ${event.title}`)];
                        },
                    },
                },
            },
            scales: {
                x: {
                    display: !compact,
                    grid: { display: false, drawTicks: false },
                    border: { display: false },
                    ticks: { color: colors['text-muted'], maxTicksLimit: 5, maxRotation: 0, padding: 10, font: { family: 'system-ui', size: 9 }, callback(value) { return formatDate(labels[value] ?? ''); } },
                },
                y: {
                    display: !compact,
                    beginAtZero: !['percent', 'score'].includes(config.unit),
                    suggestedMax: ['percent', 'score'].includes(config.unit) ? 100 : undefined,
                    grid: { color: colors['chart-grid'], drawTicks: false },
                    border: { display: false },
                    ticks: { color: colors['text-muted'], maxTicksLimit: 4, stepSize: config.unit === 'status' ? 1 : undefined, padding: 10, font: { family: 'system-ui', size: 9 }, callback(value) { return formatNumber(value, config.unit, true); } },
                },
            },
        },
    });
    chart.metribaySignature = signature;
    charts.add(chart);
    if (!compact) {
        canvas.tabIndex = 0;
        canvas.setAttribute('aria-keyshortcuts', 'ArrowLeft ArrowRight');
        canvas.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Escape'].includes(event.key)) return;
            event.preventDefault();
            const current = Chart.getChart(canvas);
            if (!current) return;
            if (event.key === 'Escape') {
                current.setActiveElements([]);
                current.tooltip.setActiveElements([], { x: 0, y: 0 });
            } else {
                const direction = event.key === 'ArrowRight' ? 1 : -1;
                const lastIndex = current.metribayCursor ?? current.getActiveElements()[0]?.index ?? (direction > 0 ? -1 : series.length);
                let index = Math.max(0, Math.min(series.length - 1, lastIndex + direction));
                const hasValue = candidate => current.data.datasets.some((dataset, datasetIndex) => current.isDatasetVisible(datasetIndex) && finite(dataset.data[candidate]) !== null);
                while (!hasValue(index) && index + direction >= 0 && index + direction < series.length) index += direction;
                current.metribayCursor = index;
                const announcement = canvas.closest('.metric')?.querySelector('[data-chart-announcement]');
                if (announcement) {
                    const parts = [];
                    if (current.isDatasetVisible(0)) parts.push(`${formatDate(series[index]?.date ?? '', true)}. ${config.label}: ${formatNumber(finite(series[index]?.value), config.unit)}${series[index]?.partial ? `. ${t('Partial date.')}` : ''}`);
                    if (hasPrevious && current.isDatasetVisible(1)) parts.push(`${t('Previous period')} ${formatDate(previous[index]?.date ?? '', true)}: ${formatNumber(finite(previous[index]?.value), config.unit)}${previous[index]?.partial ? `. ${t('Partial date.')}` : ''}`);
                    for (const event of eventsOn(series[index]?.date)) parts.push(t('Event: :title', { title: event.title }));
                    announcement.textContent = parts.join('. ');
                }
                const active = current.data.datasets.map((dataset, datasetIndex) => ({ datasetIndex, index })).filter(item => current.isDatasetVisible(item.datasetIndex) && finite(current.data.datasets[item.datasetIndex].data[index]) !== null);
                current.setActiveElements(active);
                current.tooltip.setActiveElements(active, { x: current.scales.x.getPixelForValue(index), y: current.chartArea.top });
            }
            current.update('none');
        }, { signal: chartAbortSignal(chart) });
    }
    return chart;
}
function chartAbortSignal(chart) {
    chart.metribayAbort = new AbortController();
    return chart.metribayAbort.signal;
}
function destroyChart(chart) {
    chart.metribayAbort?.abort();
    chart.destroy();
    charts.delete(chart);
}
function syncCharts() {
    for (const chart of charts) if (!chart.canvas?.isConnected) destroyChart(chart);
    document.querySelectorAll('canvas[data-metric-chart]').forEach(canvas => {
        const signature = `${canvas.dataset.chartConfig}|${root.dataset.theme}|${root.dataset.density}`;
        const existing = Chart.getChart(canvas);
        if (existing?.metribaySignature === signature) return;
        if (existing) destroyChart(existing);
        try {
            const config = JSON.parse(canvas.dataset.chartConfig);
            makeChart(canvas, config, signature);
        } catch (error) {
            console.error('A metric chart could not be rendered.', error);
            const message = document.createElement('p');
            message.className = 'field-hint';
            message.textContent = t('The chart could not load. Daily values remain available below.');
            canvas.replaceWith(message);
        }
    });
}
function filterProjects() {
    const input = document.querySelector('[data-project-search]');
    if (!input) return;
    const query = input.value.trim().toLocaleLowerCase();
    let visible = 0;
    document.querySelectorAll('[data-project-name]').forEach(project => {
        project.hidden = !project.dataset.projectName.includes(query);
        if (!project.hidden) visible++;
    });
    const empty = document.querySelector('[data-search-empty]');
    if (empty) empty.hidden = !query || visible > 0;
}
function syncPreferences() {
    const numbers = document.querySelector('[data-number-preference]');
    const density = document.querySelector('[data-density-preference]');
    if (numbers) numbers.value = storage.get('numbers', 'full');
    if (density) density.value = storage.get('density', 'comfortable');
    document.querySelectorAll('[data-theme-input]').forEach(input => { input.checked = input.value === themePreference(); });
}
function queueSync() {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
        scheduled = false;
        syncCharts();
        syncNumbers();
        filterProjects();
    });
}

function syncProviderForm(form, changed = false) {
    const selected = form.querySelector('[data-provider-switch]')?.value;
    if (!selected) return;
    form.querySelectorAll('[data-provider-panel]').forEach(panel => {
        panel.hidden = panel.dataset.providerPanel !== selected;
        panel.disabled = panel.hidden;
    });
    const connection = form.querySelector('[data-connection-select]');
    if (connection) {
        const optional = selected === 'wporg';
        const empty = connection.querySelector('option[value=""]');
        if (empty) empty.textContent = optional ? t('No credentials needed') : t('Choose a connected account');
        connection.querySelectorAll('[data-provider-kind]').forEach(option => {
            option.hidden = option.dataset.providerKind !== selected;
            option.disabled = option.hidden;
        });
        if (changed || connection.selectedOptions[0]?.disabled) connection.value = '';
        connection.required = !optional;
        const hint = form.querySelector('[data-connection-hint]');
        if (hint) hint.hidden = optional;
    }
    const timezone = form.querySelector('[data-provider-timezone]');
    const panel = [...form.querySelectorAll('[data-provider-panel]')].find(element => element.dataset.providerPanel === selected);
    if (timezone && changed && panel?.dataset.defaultTimezone) timezone.value = panel.dataset.defaultTimezone;
}

function announcePreference() {
    const status = document.querySelector('[data-preference-status]');
    if (status) status.textContent = t('Preference saved in this browser.');
}

function initialize() {
    applyTheme();
    syncPreferences();
    document.querySelector('[data-validation-errors]')?.focus();
    document.querySelectorAll('[data-provider-form]').forEach(form => syncProviderForm(form));
    queueSync();
}

document.addEventListener('click', event => {
    const opener = event.target.closest('[data-dialog-open]');
    if (opener) document.getElementById(opener.dataset.dialogOpen)?.showModal();
    const closer = event.target.closest('[data-dialog-close]');
    if (closer) closer.closest('dialog')?.close();
    const cycle = event.target.closest('[data-theme-cycle]');
    if (cycle) { const preference = themePreference(); applyTheme(preference === 'system' ? (root.dataset.theme === 'light' ? 'dark' : 'light') : ({ light: 'dark', dark: 'system' })[preference], true); }
    const legend = event.target.closest('[data-chart-toggle]');
    if (legend) {
        const canvas = legend.closest('.metric')?.querySelector('canvas');
        const chart = canvas && Chart.getChart(canvas);
        if (chart) {
            const index = Number(legend.dataset.chartToggle);
            const visible = !chart.isDatasetVisible(index);
            chart.setDatasetVisibility(index, visible);
            legend.setAttribute('aria-pressed', String(visible));
            chart.update('none');
        }
    }
    // The project name is the real link; the rest of the row is a larger click target for pointer users.
    const row = event.target.closest('[data-row-href]');
    if (row && !event.target.closest('a, button, input, select, label') && !window.getSelection()?.toString()) {
        if (event.metaKey || event.ctrlKey) window.open(row.dataset.rowHref, '_blank', 'noopener');
        else window.location.assign(row.dataset.rowHref);
    }
    const twoFactor = event.target.closest('[data-two-factor-toggle]');
    if (twoFactor) {
        const form = twoFactor.closest('form');
        const recovery = form.querySelector('[data-recovery-field]');
        const authenticator = form.querySelector('[data-authenticator-field]');
        const useRecovery = recovery.hidden;
        recovery.hidden = !useRecovery;
        authenticator.hidden = useRecovery;
        recovery.querySelector('input').disabled = !useRecovery;
        authenticator.querySelector('input').disabled = useRecovery;
        recovery.querySelector('input').required = useRecovery;
        authenticator.querySelector('input').required = !useRecovery;
        twoFactor.textContent = useRecovery ? t('Use an authenticator code') : t('Use a recovery code');
        (useRecovery ? recovery : authenticator).querySelector('input').focus();
    }
    document.querySelectorAll('.account-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
});
document.addEventListener('change', event => {
    const target = event.target;
    if (target.matches('[data-theme-input]')) { applyTheme(target.value, true); announcePreference(); }
    if (target.matches('[data-number-preference]')) { storage.set('numbers', target.value); syncNumbers(); announcePreference(); }
    if (target.matches('[data-density-preference]')) { storage.set('density', target.value); root.dataset.density = target.value; queueSync(); announcePreference(); }
    if (target.matches('[data-provider-switch]')) syncProviderForm(target.closest('form'), true);
    if (target.matches('[data-auto-submit]')) target.form.requestSubmit();
});
document.addEventListener('input', event => { if (event.target.matches('[data-project-search]')) filterProjects(); });
document.addEventListener('submit', event => {
    const form = event.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
    const button = event.submitter;
    if (button && !form.hasAttribute('wire:submit')) {
        button.dataset.originalLabel = button.textContent;
        button.setAttribute('aria-busy', 'true');
        // Defer until successful submission is underway; disabling a named submitter too early changes request semantics.
        queueMicrotask(() => { if (!event.defaultPrevented) button.disabled = true; });
    }
});
document.addEventListener('keydown', event => { if (event.key === 'Escape') document.querySelectorAll('.account-menu[open]').forEach(menu => { menu.open = false; }); });
window.addEventListener('pageshow', () => {
    document.querySelectorAll('button[aria-busy="true"]').forEach(button => { button.disabled = false; button.removeAttribute('aria-busy'); });
});
new MutationObserver(queueSync).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-chart-config'] });
document.addEventListener('livewire:navigated', initialize);
document.addEventListener('livewire:init', () => { window.Livewire.hook('morphed', queueSync); });
initialize();

function setupSourceForms() {
 document.querySelectorAll('[data-resource-search]').forEach(input => {
 input.oninput = () => document.querySelectorAll('[data-resource-row]').forEach(row => { row.hidden = !row.textContent.toLowerCase().includes(input.value.toLowerCase()); });
 });
}
document.addEventListener('DOMContentLoaded', setupSourceForms);
document.addEventListener('livewire:navigated', setupSourceForms);

if (document.querySelector('[data-import-pending]')) {
 setTimeout(() => { if (!document.querySelector('input:focus,textarea:focus')) window.location.reload(); }, 10000);
}
