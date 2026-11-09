import './usage-analytics.js';
import '../css/app.css';
import { Chart, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler } from 'chart.js';
import { setupHourlyRefresh } from './hourly-refresh.js';
import { applyGroupCollapse, fragmentTarget, openCollapsedGroup, setupGroupCollapse } from './group-collapse.js';
import { renderProjectViews, setupProjectViews } from './project-views.js';

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
function formatNumber(value, unit = 'count', compact = false, currency = null) {
    if (value === null) return t('Unavailable');
    if (unit === 'status') return ({ 0: t('Paused'), 1: t('Not checked yet'), 2: t('Up'), 8: t('Seems down'), 9: t('Down') })[value] ?? String(value);
    if (unit === 'currency' && currency) return new Intl.NumberFormat(document.documentElement.lang || 'en', { style: 'currency', currency, notation: compact && Math.abs(value) >= 1000 ? 'compact' : 'standard' }).format(value);
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
    return Object.fromEntries(['chart-line', 'chart-previous', 'chart-grid', 'text-muted', 'page', 'surface', 'text', 'event-blue', 'event-green', 'event-amber', 'event-violet', 'event-automatic'].map(name => [name, style.getPropertyValue(`--${name}`).trim()]));
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
// Hovering one chart marks the same reporting date on every chart of the page.
let crossDate = null;
function setCrosshair(date, source) {
    if (date === crossDate) return;
    crossDate = date;
    for (const chart of charts) {
        chart.metribayCrossDate = date;
        if (chart !== source) chart.draw();
    }
}
const crosshair = {
    id: 'metribayCrosshair',
    afterEvent(chart, args) {
        const { event } = args;
        if (event.type === 'mouseout' || !args.inChartArea) {
            if (crossDate !== null) { setCrosshair(null, chart); args.changed = true; }
            return;
        }
        if (!['mousemove', 'touchstart', 'touchmove'].includes(event.type)) return;
        const index = Math.round(chart.scales.x.getValueForPixel(event.x));
        const date = chart.data.labels[Math.max(0, Math.min(chart.data.labels.length - 1, index))] ?? null;
        if (date !== crossDate) { setCrosshair(date, chart); args.changed = true; }
    },
    afterDatasetsDraw(chart) {
        const index = crossDate === null ? -1 : chart.data.labels.indexOf(crossDate);
        if (index < 0) return;
        const { ctx, chartArea: { top, bottom }, scales: { x, y } } = chart;
        const colors = chart.metribayColors;
        const position = x.getPixelForValue(index);
        ctx.save();
        ctx.strokeStyle = colors['text-muted'];
        ctx.globalAlpha = .55;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(position, top);
        ctx.lineTo(position, bottom);
        ctx.stroke();
        const value = chart.isDatasetVisible(0) ? finite(chart.data.datasets[0].data[index]) : null;
        if (value !== null && !chart.tooltip?.getActiveElements().length) {
            ctx.globalAlpha = 1;
            ctx.fillStyle = colors['chart-line'];
            ctx.strokeStyle = colors.surface;
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(position, y.getPixelForValue(value), 3.5, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
        }
        ctx.restore();
    },
};
// Sizes: "l" lead chart with axes and events, "s" small chart with start and end dates, "n" sparkline under a number.
function makeChart(canvas, config, signature) {
    const colors = chartColors();
    const size = ['l', 's', 'n'].includes(config.size) ? config.size : 'l';
    const large = size === 'l';
    const tile = size === 'n';
    const series = Array.isArray(config.series) ? config.series : [];
    const previous = !tile && Array.isArray(config.previous) ? config.previous : [];
    const labels = series.map(point => point.date);
    const hasPrevious = previous.some(point => finite(point.value) !== null);
    const events = tile || !Array.isArray(config.events) ? [] : config.events.filter(event => labels.includes(event.date));
    const eventsOn = date => events.filter(event => event.date === date);
    // A marker opens its event on the events page; several on one date open that day.
    const markerAt = (chart, event) => {
        if (!config.eventsUrl || !events.length) return null;
        let nearest = null;
        for (const date of new Set(events.map(item => item.date))) {
            const distance = Math.abs(chart.scales.x.getPixelForValue(labels.indexOf(date)) - event.x);
            if (distance <= 6 && (!nearest || distance < nearest.distance)) nearest = { date, distance };
        }
        return nearest?.date ?? null;
    };
    const openEvents = date => {
        const found = eventsOn(date);
        if (!found.length || !config.eventsUrl) return;
        window.location.assign(`${config.eventsUrl}${config.eventsUrl.includes('?') ? '&' : '?'}date=${date}#${found.length === 1 ? found[0].anchor : `day-${date}`}`);
    };
    const known = index => finite(series[index]?.value) !== null;
    const isolated = index => known(index) && !known(index - 1) && !known(index + 1);
    const lastIndex = series.length - 1;
    const datasets = [{
        label: t('Selected period'),
        data: series.map(point => finite(point.value)),
        borderColor: colors['chart-line'],
        backgroundColor: `${colors['chart-line']}${tile ? '14' : '0c'}`,
        borderWidth: large ? 2 : 1.6,
        // Today's incomplete value: hollow end point and a dashed segment leading to it.
        pointRadius(context) { return context.dataIndex === lastIndex && series[lastIndex]?.partial ? (large ? 3 : 2.3) : (isolated(context.dataIndex) ? 2 : 0); },
        pointHoverRadius: tile ? 2.5 : 4,
        pointBorderWidth: 1.5,
        pointBackgroundColor(context) { return series[context.dataIndex]?.partial ? colors.surface : colors['chart-line']; },
        pointBorderColor: colors['chart-line'],
        segment: { borderDash(context) { return series[context.p1DataIndex]?.partial ? [2, 2.5] : undefined; } },
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
        borderDash: [3, 3],
        borderWidth: 1.2,
        pointRadius: 0,
        pointHoverRadius: large ? 3 : 2,
        spanGaps: false,
        tension: 0.18,
        fill: false,
        stepped: config.unit === 'status',
    });
    const group = canvas.closest('[data-chart-group]');
    for (const [index, dataset] of datasets.entries()) {
        dataset.hidden = group?.querySelector(`[data-chart-toggle="${index}"]`)?.getAttribute('aria-pressed') === 'false';
    }
    const tickFont = { family: 'system-ui', size: 10 };
    const chart = new Chart(canvas, {
        type: 'line',
        data: { labels, datasets },
        plugins: [eventMarkers, crosshair],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            normalized: true,
            interaction: { mode: 'index', intersect: false },
            onClick(event, _elements, current) { const date = markerAt(current, event); if (date) openEvents(date); },
            onHover(event, _elements, current) { current.canvas.style.cursor = markerAt(current, event) ? 'pointer' : ''; },
            layout: { padding: { top: events.length ? 10 : 4, left: 0, right: tile ? 3 : 2, bottom: tile ? 2 : 0 } },
            plugins: {
                legend: { display: false },
                metribayEvents: { events, labels, colors },
                tooltip: {
                    enabled: true,
                    backgroundColor: colors.text,
                    titleColor: colors.page,
                    bodyColor: colors.page,
                    padding: tile ? 8 : 10,
                    displayColors: large,
                    titleFont: { family: 'system-ui', size: 11, weight: 'normal' },
                    bodyFont: { family: 'system-ui', size: 11 },
                    callbacks: {
                        title(items) { return items.length ? formatDate(series[items[0].dataIndex]?.date ?? '', true) : ''; },
                        label(context) {
                            const point = (context.datasetIndex === 0 ? series : previous)[context.dataIndex];
                            const value = formatNumber(finite(context.raw), config.unit, false, config.currency);
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
                    display: !tile,
                    grid: { display: false, drawTicks: false },
                    border: { display: false },
                    ticks: large
                        ? { color: colors['text-muted'], maxTicksLimit: 5, maxRotation: 0, align: 'inner', padding: 8, font: tickFont, callback(value) { return formatDate(labels[value] ?? ''); } }
                        : { color: colors['text-muted'], autoSkip: false, maxRotation: 0, align: 'inner', padding: 5, font: tickFont, callback(value, index) { return index === 0 || index === lastIndex ? formatDate(labels[value] ?? '') : ''; } },
                },
                y: {
                    display: !tile,
                    // Number tiles show the shape of a snapshot, so their sparkline is not anchored at zero.
                    beginAtZero: !tile && !['percent', 'score'].includes(config.unit),
                    suggestedMax: !tile && ['percent', 'score'].includes(config.unit) ? 100 : undefined,
                    grid: { color: colors['chart-grid'], drawTicks: false },
                    border: { display: false },
                    ticks: large
                        ? { color: colors['text-muted'], maxTicksLimit: 4, stepSize: config.unit === 'status' ? 1 : undefined, padding: 8, font: tickFont, callback(value) { return formatNumber(value, config.unit, true, config.currency); } }
                        : { display: false, maxTicksLimit: 3 },
                },
            },
        },
    });
    chart.metribaySignature = signature;
    chart.metribayColors = colors;
    chart.metribayCrossDate = crossDate;
    charts.add(chart);
    if (!tile) {
        canvas.tabIndex = 0;
        canvas.setAttribute('aria-keyshortcuts', 'ArrowLeft ArrowRight');
        canvas.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Escape', 'Enter'].includes(event.key)) return;
            const current = Chart.getChart(canvas);
            if (!current) return;
            if (event.key === 'Enter') {
                const date = series[current.metribayCursor]?.date;
                if (date && eventsOn(date).length) { event.preventDefault(); openEvents(date); }
                return;
            }
            event.preventDefault();
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
                    if (current.isDatasetVisible(0)) parts.push(`${formatDate(series[index]?.date ?? '', true)}. ${config.label}: ${formatNumber(finite(series[index]?.value), config.unit, false, config.currency)}${series[index]?.partial ? `. ${t('Partial date.')}` : ''}`);
                    if (hasPrevious && current.isDatasetVisible(1)) parts.push(`${t('Previous period')} ${formatDate(previous[index]?.date ?? '', true)}: ${formatNumber(finite(previous[index]?.value), config.unit, false, config.currency)}${previous[index]?.partial ? `. ${t('Partial date.')}` : ''}`);
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
        if (canvas.closest('[data-group-body][hidden]')) return;
        const chartConfig = canvas.closest('[data-chart-container]').dataset.chartConfig;
        const signature = `${chartConfig}|${root.dataset.theme}|${root.dataset.density}`;
        const existing = Chart.getChart(canvas);
        if (existing?.metribaySignature === signature) return;
        if (existing) destroyChart(existing);
        try {
            const config = JSON.parse(chartConfig);
            canvas.setAttribute('aria-label', config.ariaLabel);
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
// Top queries: the chosen source, list and expanded state survive the hourly refresh, which re-renders the block.
const queryState = new Map();
function showQueries(block) {
    const state = queryState.get(block.id) ?? {};
    const sources = [...block.querySelectorAll('[data-query-source-panel]')];
    const source = sources.find(panel => panel.dataset.querySourcePanel === state.source) ?? sources[0];
    if (!source) return;
    const views = [...block.querySelectorAll('[data-query-view]')];
    const view = views.some(tab => tab.dataset.queryView === state.view) ? state.view : views.find(tab => tab.getAttribute('aria-selected') === 'true')?.dataset.queryView;
    const counts = (source.dataset.queryCounts || '').split(',');
    sources.forEach(panel => { panel.hidden = panel !== source; });
    block.querySelectorAll('[data-query-source]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.querySource === source.dataset.querySourcePanel)));
    views.forEach((tab, index) => {
        const selected = tab.dataset.queryView === view;
        tab.setAttribute('aria-selected', String(selected));
        tab.tabIndex = selected ? 0 : -1;
        tab.setAttribute('aria-controls', `${block.id}-${source.dataset.querySourcePanel}-${tab.dataset.queryView}`);
        const count = tab.querySelector('[data-query-count]');
        if (count && counts[index] !== undefined) {
            const text = new Intl.NumberFormat(document.documentElement.lang || 'en').format(Number(counts[index]));
            if (count.textContent !== text) count.textContent = text;
        }
    });
    source.querySelectorAll('[data-query-panel]').forEach(panel => {
        panel.hidden = panel.dataset.queryPanel !== view;
        const expanded = (state.expanded ?? []).includes(panel.id);
        panel.querySelectorAll('[data-query-extra]').forEach(row => { row.hidden = !expanded; });
        const more = panel.querySelector('[data-query-more]');
        if (more) {
            more.setAttribute('aria-expanded', String(expanded));
            const text = expanded ? more.dataset.labelLess : more.dataset.labelMore;
            if (more.textContent !== text) more.textContent = text;
        }
    });
}
function syncQueries() { document.querySelectorAll('[data-query-block]').forEach(block => { if (queryState.has(block.id)) showQueries(block); }); }
function updateQueries(block, change) {
    const state = { expanded: [], ...(queryState.get(block.id) ?? {}) };
    change(state);
    queryState.set(block.id, state);
    showQueries(block);
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
        applyGroupCollapse();
        renderProjectViews();
        syncCharts();
        syncNumbers();
        syncQueries();
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

// A linked event may sit in a collapsed bundle: open it, bring it into view and mark it briefly.
function revealTarget() {
    const target = fragmentTarget(window.location.hash);
    if (!target || target.tagName === 'DIALOG') return;
    const bundle = target.closest('details');
    if (bundle && !bundle.open) bundle.open = true;
    const opened = openCollapsedGroup(target);
    if (target.matches('[data-query-panel]')) {
        const block = target.closest('[data-query-block]');
        const source = target.closest('[data-query-source-panel]');
        if (block && source) updateQueries(block, state => {
            state.source = source.dataset.querySourcePanel;
            state.view = target.dataset.queryPanel;
        });
    }
    const finish = () => {
        target.scrollIntoView({ block: 'center' });
        target.classList.add('is-target');
        if (target.closest('[data-chart-group]')) {
            const heading = target.matches('h1, h2, h3') ? target : target.querySelector('h2, h3');
            const destination = heading || target;
            if (!destination.matches('a, button, input, select, textarea, summary') && !destination.hasAttribute('tabindex')) destination.tabIndex = -1;
            destination.focus({ preventScroll: true });
        }
        setTimeout(() => target.classList.remove('is-target'), 2400);
    };
    // Charts inside a group that was folded need a frame to take their size before the scroll.
    if (opened || target.closest('[data-group-body]')) requestAnimationFrame(() => requestAnimationFrame(finish));
    else finish();
}
window.addEventListener('hashchange', revealTarget);

function initialize() {
    applyTheme();
    syncPreferences();
    applyGroupCollapse();
    setupProjectViews();
    // A form sent from a dialog reopens it when it comes back with errors.
    document.querySelectorAll('dialog[data-open-on-load]').forEach(dialog => { if (!dialog.open) dialog.showModal(); });
    revealTarget();
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
    // One legend per group toggles that series on every chart of the group.
    const legend = event.target.closest('[data-chart-toggle]');
    if (legend) {
        const index = Number(legend.dataset.chartToggle);
        const visible = legend.getAttribute('aria-pressed') === 'false';
        legend.setAttribute('aria-pressed', String(visible));
        legend.closest('[data-chart-group]')?.querySelectorAll('canvas[data-metric-chart]').forEach(canvas => {
            const chart = Chart.getChart(canvas);
            if (!chart?.data.datasets[index]) return;
            chart.setDatasetVisibility(index, visible);
            chart.update('none');
        });
    }
    const queryBlock = event.target.closest('[data-query-block]');
    const queryView = event.target.closest('[data-query-view]');
    const querySource = event.target.closest('[data-query-source]');
    const queryMore = event.target.closest('[data-query-more]');
    if (queryBlock && queryView) updateQueries(queryBlock, state => { state.view = queryView.dataset.queryView; });
    if (queryBlock && querySource) updateQueries(queryBlock, state => { state.source = querySource.dataset.querySource; });
    if (queryBlock && queryMore) {
        const panel = queryMore.closest('[data-query-panel]');
        updateQueries(queryBlock, state => { state.expanded = state.expanded.includes(panel.id) ? state.expanded.filter(id => id !== panel.id) : [...state.expanded, panel.id]; });
        if (queryMore.getAttribute('aria-expanded') === 'false') queryMore.scrollIntoView({ block: 'nearest' });
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
    document.querySelectorAll('.account-menu[open], .range-picker[open], .view-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
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
document.addEventListener('keydown', event => {
    // Arrow keys move between tabs of a tab list; the selected tab is the only one in the tab order.
    const tab = event.target.closest('[role="tab"]');
    if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    const tabs = [...tab.closest('[role="tablist"]').querySelectorAll('[role="tab"]')];
    const index = tabs.indexOf(tab);
    const next = tabs[{ ArrowLeft: (index - 1 + tabs.length) % tabs.length, ArrowRight: (index + 1) % tabs.length, Home: 0, End: tabs.length - 1 }[event.key]];
    event.preventDefault();
    next.focus();
    next.click();
});
document.addEventListener('keydown', event => { if (event.key === 'Escape') document.querySelectorAll('.account-menu[open], .range-picker[open], .view-menu[open]').forEach(menu => { menu.open = false; menu.querySelector('summary')?.focus(); }); });
document.addEventListener('focusin', event => { document.querySelectorAll('.account-menu[open], .range-picker[open], .view-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; }); });
window.addEventListener('pageshow', () => {
    document.querySelectorAll('button[aria-busy="true"]').forEach(button => { button.disabled = false; button.removeAttribute('aria-busy'); });
});
new MutationObserver(queueSync).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-chart-config'] });
document.addEventListener('livewire:navigated', initialize);
document.addEventListener('livewire:init', () => { window.Livewire.hook('morphed', queueSync); });
setupGroupCollapse(queueSync);
initialize();

function setupSourceForms() {
 document.querySelectorAll('[data-resource-search]').forEach(input => {
 input.oninput = () => document.querySelectorAll('[data-resource-row]').forEach(row => { row.hidden = !row.textContent.toLowerCase().includes(input.value.toLowerCase()); });
 });
}
document.addEventListener('DOMContentLoaded', setupSourceForms);
document.addEventListener('livewire:navigated', setupSourceForms);

setupHourlyRefresh();
