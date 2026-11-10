/** Saved project views. Names live in the browser for one viewer, workspace and project. The URL carries the groups. */
import { KNOWN_GROUPS } from './group-collapse.js';

export const VIEW_STORAGE_VERSION = 'v1';
export const MAX_SAVED_VIEWS = 12;
export const MAX_VIEW_NAME = 40;

export function viewStorageKey(viewerId, workspaceId, projectId) {
    return `metribay.views.${VIEW_STORAGE_VERSION}.${viewerId}.${workspaceId}.${projectId}`;
}

export function cleanName(name) {
    return String(name ?? '').replace(/[\u0000-\u001F\u007F]/g, '').trim().slice(0, MAX_VIEW_NAME);
}

/** @returns {string[]} Known keys only, in group order. */
export function normalizeGroups(groups, known = KNOWN_GROUPS) {
    if (!Array.isArray(groups)) return [];
    const allow = new Set(known);
    const seen = new Set();
    for (const key of groups) {
        if (typeof key === 'string' && allow.has(key)) seen.add(key);
    }
    return known.filter(key => seen.has(key));
}

export function parseViews(raw, known = KNOWN_GROUPS) {
    let parsed;
    try { parsed = JSON.parse(raw); } catch { return []; }
    if (!Array.isArray(parsed)) return [];
    const views = [];
    const names = new Set();
    const ids = new Set();
    for (const entry of parsed) {
        if (!entry || typeof entry !== 'object') continue;
        const id = typeof entry.id === 'string' && /^[a-z0-9]{8,16}$/.test(entry.id) ? entry.id : '';
        const name = typeof entry.name === 'string' ? cleanName(entry.name) : '';
        const groups = normalizeGroups(entry.groups, known);
        if (!id || !name || groups.length === 0 || ids.has(id) || names.has(name)) continue;
        ids.add(id);
        names.add(name);
        views.push({ id, name, groups });
        if (views.length === MAX_SAVED_VIEWS) break;
    }
    return views;
}

export function newViewId() {
    const alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
    const bytes = new Uint8Array(12);
    if (globalThis.crypto?.getRandomValues) globalThis.crypto.getRandomValues(bytes);
    else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    return [...bytes].map(byte => alphabet[byte % alphabet.length]).join('');
}

/**
 * Insert or replace a view. A matching name updates that view. A new name past the limit is refused.
 * @returns {{views: object[], error: string|null, saved: object|null}}
 */
export function upsertView(views, view, known = KNOWN_GROUPS, max = MAX_SAVED_VIEWS) {
    const name = cleanName(view?.name);
    const groups = normalizeGroups(view?.groups, known);
    if (!name) return { views, error: 'name', saved: null };
    if (groups.length === 0) return { views, error: 'groups', saved: null };
    const index = views.findIndex(entry => entry.name === name);
    if (index === -1 && views.length >= max) return { views, error: 'limit', saved: null };
    const id = index === -1 ? (typeof view.id === 'string' && /^[a-z0-9]{8,16}$/.test(view.id) ? view.id : newViewId()) : views[index].id;
    const saved = { id, name, groups };
    const next = views.slice();
    if (index === -1) next.push(saved);
    else next[index] = saved;
    return { views: next, error: null, saved };
}

/** @returns {{views: object[], error: string|null}} */
export function renameView(views, id, name) {
    const clean = cleanName(name);
    if (!clean) return { views, error: 'name' };
    const index = views.findIndex(entry => entry.id === id);
    if (index === -1) return { views, error: 'missing' };
    if (views.some(entry => entry.id !== id && entry.name === clean)) return { views, error: 'taken' };
    const next = views.slice();
    next[index] = { ...next[index], name: clean };
    return { views: next, error: null };
}

export function removeView(views, id) {
    return views.filter(entry => entry.id !== id);
}

/** True when the path is the same page, ignoring encoding and the fragment. */
export function sameAddress(current, path) {
    const base = current instanceof URL ? current : new URL(current, 'http://localhost');
    const next = new URL(path, base);
    if (next.pathname !== base.pathname) return false;
    const keys = new Set([...next.searchParams.keys(), ...base.searchParams.keys()]);
    for (const key of keys) {
        const left = next.searchParams.getAll(key);
        const right = base.searchParams.getAll(key);
        if (left.length !== right.length || left.some((value, index) => value !== right[index])) return false;
    }
    return true;
}

export function sameGroups(left, right) {
    const a = normalizeGroups(left);
    const b = normalizeGroups(right);
    return a.length === b.length && a.every((key, index) => key === b[index]);
}

/** Period and archive fields from the custom-view form, plus the chosen groups. */
export function viewPath(action, form, groups) {
    const url = new URL(action, 'http://localhost');
    const fields = new FormData(form);
    for (const [key, value] of fields) {
        if (key === 'groups' || key === 'groups[]' || value === '') continue;
        url.searchParams.append(key, value);
    }
    const selected = normalizeGroups(groups);
    if (selected.length) url.searchParams.set('groups', selected.join(','));
    return `${url.pathname}${url.search}`;
}

const memory = new Map();
let storageFailed = false;
let installed = false;

function browserStorage() {
    try { return globalThis.localStorage; } catch { return null; }
}

export function readViews(key, known = KNOWN_GROUPS) {
    if (!key) return [];
    if (storageFailed) return parseViews(JSON.stringify(memory.get(key) ?? []), known);
    const storage = browserStorage();
    if (!storage) { storageFailed = true; return []; }
    try { return parseViews(storage.getItem(key), known); } catch { storageFailed = true; return memory.get(key) ?? []; }
}

export function writeViews(key, views, known = KNOWN_GROUPS) {
    const stored = parseViews(JSON.stringify(views), known);
    if (!key) { memory.set('page', stored); storageFailed = true; return stored; }
    if (storageFailed) { memory.set(key, stored); return stored; }
    const storage = browserStorage();
    if (!storage) { storageFailed = true; memory.set(key, stored); return stored; }
    try { storage.setItem(key, JSON.stringify(stored)); } catch { storageFailed = true; memory.set(key, stored); }
    return stored;
}

function scopeOf(menu) {
    return menu.closest('[data-group-scope]');
}

function storageKeyFor(menu) {
    const scope = scopeOf(menu);
    if (!scope?.dataset.viewerId || !scope.dataset.workspaceId || !scope.dataset.projectId) return null;
    return viewStorageKey(scope.dataset.viewerId, scope.dataset.workspaceId, scope.dataset.projectId);
}

function knownFor(menu) {
    const listed = (menu.dataset.knownGroups || '').split(',').filter(key => KNOWN_GROUPS.includes(key));
    return listed.length ? listed : KNOWN_GROUPS;
}

function checkedGroups(menu) {
    return [...menu.querySelectorAll('[data-group]:checked')].map(input => input.value);
}

function message(menu, name, values = {}) {
    let text = menu.dataset[name] || '';
    for (const [key, value] of Object.entries(values)) text = text.replaceAll(`:${key}`, value);
    const status = menu.querySelector('[data-view-status]');
    if (status) status.textContent = text;
    return text;
}

function activeGroups(menu) {
    return normalizeGroups((menu.dataset.activeGroups || '').split(','));
}

function fillRow(row, view, menu) {
    const link = row.querySelector('[data-saved-link]');
    const input = row.querySelector('[data-saved-input]');
    const form = menu.querySelector('[data-custom-view-form]');
    link.href = viewPath(form.action, form, view.groups);
    link.textContent = view.name;
    input.value = view.name;
    row.dataset.id = view.id;
    const current = menu.dataset.match === 'custom' && sameGroups(view.groups, activeGroups(menu));
    if (current) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
}

export function renderProjectViews() {
    if (document.activeElement?.matches?.('[data-saved-input], [data-view-name]')) return;
    document.querySelectorAll('[data-project-views]').forEach(menu => {
        const list = menu.querySelector('[data-saved-views]');
        const template = menu.querySelector('[data-saved-view-template]');
        const empty = menu.querySelector('[data-saved-empty]');
        const heading = menu.querySelector('[data-saved-heading]');
        const form = menu.querySelector('[data-custom-view-form]');
        if (!list || !template || !form) return;
        const views = readViews(storageKeyFor(menu));
        // The page observer re-renders after DOM edits. Skip when this list already matches.
        const signature = JSON.stringify([menu.dataset.match, menu.dataset.activeGroups, viewPath(form.action, form, []), views]);
        if (list.dataset.rendered === signature) return;
        list.dataset.rendered = signature;
        list.replaceChildren();
        for (const view of views) {
            const row = template.content.firstElementChild.cloneNode(true);
            fillRow(row, view, menu);
            list.append(row);
        }
        if (empty) empty.hidden = views.length > 0;
        if (heading) heading.hidden = views.length === 0;
        const summary = menu.querySelector('summary');
        const match = views.find(view => menu.dataset.match === 'custom' && sameGroups(view.groups, activeGroups(menu)));
        if (summary && menu.dataset.summaryLabel) {
            summary.textContent = match ? match.name : menu.dataset.summaryLabel;
        }
    });
}

function onClick(event) {
    const menu = event.target.closest?.('[data-project-views]');
    if (!menu) return;
    const rename = event.target.closest('[data-saved-rename]');
    const renameSave = event.target.closest('[data-saved-rename-save]');
    const remove = event.target.closest('[data-saved-remove]');
    const save = event.target.closest('[data-view-save]');
    if (rename) {
        const row = rename.closest('[data-saved-row]');
        row.querySelector('[data-saved-link]').hidden = true;
        const input = row.querySelector('[data-saved-input]');
        input.hidden = false;
        rename.hidden = true;
        row.querySelector('[data-saved-rename-save]').hidden = false;
        input.focus();
        input.select();
        return;
    }
    if (renameSave) {
        const row = renameSave.closest('[data-saved-row]');
        const key = storageKeyFor(menu);
        const result = renameView(readViews(key), row.dataset.id, row.querySelector('[data-saved-input]').value);
        if (result.error) {
            message(menu, result.error === 'taken' ? 'msgTaken' : 'msgName');
            row.querySelector('[data-saved-input]').focus();
            return;
        }
        writeViews(key, result.views);
        message(menu, 'msgRenamed');
        if (document.activeElement?.matches?.('[data-saved-input]')) document.activeElement.blur();
        renderProjectViews();
        return;
    }
    if (remove) {
        const row = remove.closest('[data-saved-row]');
        const key = storageKeyFor(menu);
        writeViews(key, removeView(readViews(key), row.dataset.id));
        message(menu, 'msgRemoved');
        renderProjectViews();
        return;
    }
    if (!save) return;
    const known = knownFor(menu);
    const key = storageKeyFor(menu);
    const name = menu.querySelector('[data-view-name]')?.value ?? '';
    const result = upsertView(readViews(key), { name, groups: checkedGroups(menu) }, known, Number(menu.dataset.max) || MAX_SAVED_VIEWS);
    if (result.error) {
        message(menu, result.error === 'limit' ? 'msgLimit' : (result.error === 'groups' ? 'msgGroups' : 'msgName'), { count: menu.dataset.max || String(MAX_SAVED_VIEWS) });
        menu.querySelector(result.error === 'groups' ? '[data-group]' : '[data-view-name]')?.focus();
        return;
    }
    writeViews(key, result.views);
    const form = menu.querySelector('[data-custom-view-form]');
    const path = viewPath(form.action, form, result.saved.groups);
    if (!sameAddress(window.location.href, path)) {
        window.location.assign(path);
        return;
    }
    message(menu, 'msgSaved');
    const nameField = menu.querySelector('[data-view-name]');
    if (nameField) {
        nameField.value = '';
        if (document.activeElement === nameField) nameField.blur();
    }
    renderProjectViews();
}

function onSubmit(event) {
    const form = event.target;
    if (!form?.matches?.('[data-custom-view-form]')) return;
    event.preventDefault();
    const menu = form.closest('[data-project-views]');
    const groups = checkedGroups(menu);
    window.location.assign(viewPath(form.action, form, groups));
}

function onKey(event) {
    if (event.target.matches?.('[data-view-name]') && event.key === 'Enter') {
        event.preventDefault();
        event.stopPropagation();
        event.target.closest('[data-project-views]')?.querySelector('[data-view-save]')?.click();
        return;
    }
    const input = event.target.closest?.('[data-saved-input]');
    if (!input || !['Enter', 'Escape'].includes(event.key)) return;
    event.stopPropagation();
    const row = input.closest('[data-saved-row]');
    event.preventDefault();
    if (event.key === 'Escape') {
        input.value = row.querySelector('[data-saved-link]').textContent;
        input.hidden = true;
        row.querySelector('[data-saved-link]').hidden = false;
        row.querySelector('[data-saved-rename]').hidden = false;
        row.querySelector('[data-saved-rename-save]').hidden = true;
        row.querySelector('[data-saved-rename]').focus();
        return;
    }
    row.querySelector('[data-saved-rename-save]').click();
}

export function setupProjectViews() {
    renderProjectViews();
    if (installed || typeof document === 'undefined') return;
    installed = true;
    document.addEventListener('click', onClick);
    document.addEventListener('submit', onSubmit);
    document.addEventListener('keydown', onKey, true);
}

/** Test hook. */
export function resetProjectViews() {
    memory.clear();
    storageFailed = false;
}
