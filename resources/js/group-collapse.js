/** Collapsible project groups. The preference is per viewer, workspace and project, and makes no server write. */
export const GROUP_STORAGE_VERSION = 'v1';
export const KNOWN_GROUPS = ['revenue', 'audience', 'search', 'development', 'distribution', 'content', 'reliability', 'page_speed'];

export function storageKey(viewerId, workspaceId, projectId) {
    return `metribay.groups.${GROUP_STORAGE_VERSION}.${viewerId}.${workspaceId}.${projectId}`;
}

export function fragmentTarget(hash) {
    try {
        const id = decodeURIComponent(hash.slice(1));
        return id ? document.getElementById(id) : null;
    } catch { return null; }
}

/** @returns {string[]} Known group keys only, in stored order, without duplicates. */
export function parseStored(raw, known = KNOWN_GROUPS) {
    let parsed;
    try { parsed = JSON.parse(raw); } catch { return []; }
    if (!Array.isArray(parsed)) return [];
    const allow = new Set(known);
    return [...new Set(parsed.filter(key => typeof key === 'string' && allow.has(key)))];
}

const memory = new Map();
const openedByLink = new Set();
let storageFailed = false;
let appliedScope = null;
let onLayout = () => {};
let installed = false;

function browserStorage() {
    try { return globalThis.localStorage; } catch { return null; }
}

export function readCollapsed(key, known = KNOWN_GROUPS) {
    if (!key) return [];
    if (storageFailed) return parseStored(JSON.stringify(memory.get(key) ?? []), known);
    const storage = browserStorage();
    if (!storage) { storageFailed = true; return []; }
    try { return parseStored(storage.getItem(key), known); } catch { storageFailed = true; return memory.get(key) ?? []; }
}

export function writeCollapsed(key, keys, known = KNOWN_GROUPS) {
    const stored = parseStored(JSON.stringify(keys), known);
    if (!key) { memory.set('page', stored); storageFailed = true; return; }
    if (storageFailed) { memory.set(key, stored); return; }
    const storage = browserStorage();
    if (!storage) { storageFailed = true; memory.set(key, stored); return; }
    try { storage.setItem(key, JSON.stringify(stored)); } catch { storageFailed = true; memory.set(key, stored); }
}

function scopeElement() {
    return document.querySelector('[data-group-scope]');
}

function knownKeys(scope) {
    const listed = (scope?.dataset.groupKeys || '').split(',').filter(key => KNOWN_GROUPS.includes(key));
    return listed.length ? listed : KNOWN_GROUPS;
}

function currentKey(scope = scopeElement()) {
    if (!scope?.dataset.viewerId || !scope.dataset.workspaceId || !scope.dataset.projectId) return null;
    return storageKey(scope.dataset.viewerId, scope.dataset.workspaceId, scope.dataset.projectId);
}

function collapsedNow(scope = scopeElement()) {
    const known = knownKeys(scope);
    return readCollapsed(currentKey(scope), known).filter(key => !openedByLink.has(key));
}

function setExpanded(group, expanded) {
    const toggle = group.querySelector('[data-group-toggle]');
    const body = group.querySelector('[data-group-body]');
    if (!toggle || !body) return;
    toggle.setAttribute('aria-expanded', String(expanded));
    body.hidden = !expanded;
    const label = toggle.querySelector('.sr-only');
    const text = expanded ? toggle.dataset.labelExpanded : toggle.dataset.labelCollapsed;
    if (label && label.textContent !== text) label.textContent = text;
}

function updateExpandAll() {
    const collapsed = [...document.querySelectorAll('[data-group-toggle]')].some(button => button.getAttribute('aria-expanded') === 'false');
    document.querySelectorAll('[data-group-recovery]').forEach(control => { control.hidden = !collapsed; });
}

export function applyGroupCollapse() {
    const scope = scopeElement();
    if (!scope) return;
    const scopeId = currentKey(scope) ?? '';
    if (scopeId !== appliedScope) {
        openedByLink.clear();
        appliedScope = scopeId;
    }
    const collapsed = new Set(collapsedNow(scope));
    scope.querySelectorAll('[data-collapsible]').forEach(group => setExpanded(group, !collapsed.has(group.dataset.groupKey)));
    updateExpandAll();
}

/** Opens a collapsed group for a jump without saving that as the preference. */
export function openCollapsedGroup(target) {
    const group = target?.closest?.('[data-collapsible]');
    if (!group) return false;
    const toggle = group.querySelector('[data-group-toggle]');
    if (!toggle || toggle.getAttribute('aria-expanded') !== 'false') return false;
    if (group.dataset.groupKey) openedByLink.add(group.dataset.groupKey);
    setExpanded(group, true);
    updateExpandAll();
    onLayout();
    return true;
}

function remember(key, change) {
    const scope = scopeElement();
    const known = knownKeys(scope);
    const stored = new Set(readCollapsed(currentKey(scope), known));
    change(stored);
    if (key) openedByLink.delete(key);
    writeCollapsed(currentKey(scope), [...stored], known);
}

function onClick(event) {
    const toggle = event.target.closest?.('[data-group-toggle]');
    if (toggle) {
        const group = toggle.closest('[data-collapsible]');
        const key = group?.dataset.groupKey;
        const expanded = toggle.getAttribute('aria-expanded') !== 'false';
        remember(key, stored => { if (expanded) stored.add(key); else stored.delete(key); });
        if (group) setExpanded(group, !expanded);
        updateExpandAll();
        onLayout();
        return;
    }
    const expandAll = event.target.closest?.('[data-group-expand-all]');
    if (expandAll) {
        const focusTarget = document.activeElement === expandAll
            ? scopeElement()?.querySelector('[data-group-toggle][aria-expanded="false"]')
            : null;
        openedByLink.clear();
        writeCollapsed(currentKey(), [], knownKeys(scopeElement()));
        applyGroupCollapse();
        focusTarget?.focus();
        onLayout();
        return;
    }
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest?.('a[href]');
    if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;
    let url;
    try { url = new URL(link.href, window.location.href); } catch { return; }
    if (url.origin !== window.location.origin || url.pathname !== window.location.pathname || url.search !== window.location.search || !url.hash) return;
    const target = fragmentTarget(url.hash);
    if (!target) return;
    if (url.hash === window.location.hash) {
        event.preventDefault();
        window.dispatchEvent(new HashChangeEvent('hashchange'));
        return;
    }
    // Open before the browser scrolls to a group that is still folded.
    openCollapsedGroup(target);
}

export function setupGroupCollapse(onLayoutChange = () => {}) {
    onLayout = onLayoutChange;
    if (installed || typeof document === 'undefined') return;
    installed = true;
    document.addEventListener('click', onClick);
}

/** Test hook. Resets the module memory so cases do not leak into each other. */
export function resetGroupCollapse() {
    memory.clear();
    openedByLink.clear();
    appliedScope = null;
    storageFailed = false;
}
