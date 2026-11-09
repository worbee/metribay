import assert from 'node:assert/strict';
import { test } from 'node:test';
import { GROUP_STORAGE_VERSION, KNOWN_GROUPS, parseStored, readCollapsed, resetGroupCollapse, storageKey, writeCollapsed } from '../../resources/js/group-collapse.js';

test('the storage key is versioned for one viewer, workspace and project', () => {
    assert.equal(GROUP_STORAGE_VERSION, 'v1');
    assert.equal(storageKey('4', '8', '15'), 'metribay.groups.v1.4.8.15');
});

test('stored keys stay inside the known group list', () => {
    assert.deepEqual(parseStored(JSON.stringify(['search', 'search', 'nope', 3]), KNOWN_GROUPS), ['search']);
    assert.deepEqual(parseStored('not-json', KNOWN_GROUPS), []);
    assert.deepEqual(parseStored('{"search":true}', KNOWN_GROUPS), []);
    assert.deepEqual(parseStored(null, KNOWN_GROUPS), []);
});

test('a storage failure keeps the preference in memory for this page', () => {
    resetGroupCollapse();
    const key = storageKey('1', '2', '3');
    globalThis.localStorage = {
        getItem() { throw new Error('blocked'); },
        setItem() { throw new Error('blocked'); },
    };
    assert.deepEqual(readCollapsed(key), []);
    writeCollapsed(key, ['search', 'made-up', 'search']);
    assert.deepEqual(readCollapsed(key), ['search']);
    delete globalThis.localStorage;
    resetGroupCollapse();
});
