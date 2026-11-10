import assert from 'node:assert/strict';
import { test } from 'node:test';
import { KNOWN_GROUPS } from '../../resources/js/group-collapse.js';
import { cleanName, normalizeGroups, parseViews, readViews, removeView, renameView, resetProjectViews, sameAddress, sameGroups, upsertView, viewPath, viewStorageKey, writeViews } from '../../resources/js/project-views.js';

test('the storage key is versioned for one viewer, workspace and project', () => {
    assert.equal(viewStorageKey('4', '8', '15'), 'metribay.views.v1.4.8.15');
});

test('names and groups stay inside the limits', () => {
    assert.equal(cleanName('  Launch\nweek  '), 'Launchweek');
    assert.equal(cleanName('x'.repeat(50)).length, 40);
    assert.deepEqual(normalizeGroups(['nope', 'search', 'search', 'audience'], KNOWN_GROUPS), ['audience', 'search']);
});

test('stored views drop unknown, duplicate and incomplete entries', () => {
    const raw = JSON.stringify([
        { id: 'eeeeeeee', name: 'Broken groups', groups: { search: true } },
        { id: 'ffffffff', name: { invalid: true }, groups: ['search'] },
        { id: 'abcdef12', name: 'Launch', groups: ['search', 'nope', 'search'] },
        { id: 'abcdef12', name: 'Again', groups: ['audience'] },
        { id: 'short', name: 'Nope', groups: ['search'] },
        { id: 'bbbbbbbb', name: '  ', groups: ['search'] },
        { id: 'cccccccc', name: 'Empty', groups: [] },
        { id: 'dddddddd', name: 'Launch', groups: ['audience'] },
    ]);
    assert.deepEqual(parseViews(raw), [{ id: 'abcdef12', name: 'Launch', groups: ['search'] }]);
    assert.deepEqual(parseViews('nope'), []);
});

test('saving updates the same name and refuses a thirteenth view', () => {
    const first = upsertView([], { id: 'abcdef12', name: 'Launch', groups: ['search', 'audience'] });
    assert.equal(first.error, null);
    assert.deepEqual(first.saved.groups, ['audience', 'search']);
    const renamedGroups = upsertView(first.views, { name: 'Launch', groups: ['revenue'] });
    assert.equal(renamedGroups.views.length, 1);
    assert.deepEqual(renamedGroups.saved.groups, ['revenue']);
    let views = [];
    for (let i = 0; i < 12; i++) views = upsertView(views, { name: `View ${i}`, groups: ['search'] }).views;
    assert.equal(upsertView(views, { name: 'One more', groups: ['search'] }).error, 'limit');
    assert.equal(upsertView(views, { name: 'View 0', groups: ['audience'] }).error, null);
    assert.equal(upsertView(views, { name: '   ', groups: ['search'] }).error, 'name');
    assert.equal(upsertView(views, { name: 'Named', groups: [] }).error, 'groups');
});

test('rename and remove keep the other views', () => {
    const views = [
        { id: 'abcdef12', name: 'Launch', groups: ['search'] },
        { id: 'bbbbbbbb', name: 'Money', groups: ['revenue'] },
    ];
    assert.equal(renameView(views, 'abcdef12', 'Money').error, 'taken');
    assert.equal(renameView(views, 'abcdef12', '  ').error, 'name');
    assert.equal(renameView(renameView(views, 'abcdef12', 'Shipped').views, 'missing', 'X').error, 'missing');
    assert.deepEqual(removeView(views, 'abcdef12').map(view => view.name), ['Money']);
    assert.equal(sameGroups(['search', 'audience'], ['audience', 'search']), true);
});

test('the view path keeps the period and archive and drops an empty mix', () => {
    const form = { action: 'http://localhost/w/layout/projects/4' };
    const fields = new Map([['days', '7'], ['queries', '2026-10'], ['compare', ''], ['groups[]', 'search']]);
    form[Symbol.iterator] = undefined;
    const original = globalThis.FormData;
    globalThis.FormData = class {
        * [Symbol.iterator]() {
            yield ['days', '7'];
            yield ['queries', '2026-10'];
            yield ['compare', ''];
            yield ['groups[]', 'search'];
        }
    };
    const path = viewPath(form.action, form, ['search', 'audience']);
    assert.equal(path, '/w/layout/projects/4?days=7&queries=2026-10&groups=audience%2Csearch');
    assert.equal(sameAddress(`http://localhost${path.replaceAll('%2C', ',')}`, path), true);
    assert.equal(sameAddress('http://localhost/w/layout/projects/4?days=7&queries=2026-10&groups=audience,search#group-search', path), true);
    assert.equal(sameAddress('http://localhost/w/layout/projects/4?days=30&groups=audience,search', path), false);
    assert.equal(viewPath(form.action, form, []), '/w/layout/projects/4?days=7&queries=2026-10');
    globalThis.FormData = original;
    assert.equal(fields.get('days'), '7');
});

test('a storage failure keeps saved views for this page', () => {
    resetProjectViews();
    const key = viewStorageKey('1', '2', '3');
    globalThis.localStorage = {
        getItem() { throw new Error('blocked'); },
        setItem() { throw new Error('blocked'); },
    };
    assert.deepEqual(readViews(key), []);
    writeViews(key, [{ id: 'abcdef12', name: 'Launch', groups: ['search', 'nope'] }]);
    assert.deepEqual(readViews(key), [{ id: 'abcdef12', name: 'Launch', groups: ['search'] }]);
    delete globalThis.localStorage;
    resetProjectViews();
});
