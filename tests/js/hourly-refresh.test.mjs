import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { test } from 'node:test';

const source = readFileSync(new URL('../../resources/js/hourly-refresh.js', import.meta.url), 'utf8').replace('export ', '');
function fixture(hidden = false) {
    let time = 0;
    let calls = 0;
    const listeners = {};
    let tick;
    const element = { getAttribute: () => 'fixture-component' };
    const document = { hidden, querySelectorAll: () => [element], addEventListener: (event, fn) => { listeners[event] = fn; } };
    const context = vm.createContext({ document, window: { Livewire: { find: () => ({ $refresh: async () => { calls++; } }) } }, Date: { now: () => time }, WeakMap, setInterval: (fn, delay) => { assert.equal(delay, 60_000); tick = fn; } });
    vm.runInContext(source + '\nsetupHourlyRefresh();', context);
    return { document, listeners, tick: () => tick(), advance: amount => { time += amount; }, calls: () => calls };
}
test('both dashboard types wait an hour, refresh once, then wait another hour', async () => {
    const f = fixture();
    await f.tick();
    assert.equal(f.calls(), 0);
    f.advance(3_599_999);
    await f.tick();
    assert.equal(f.calls(), 0);
    f.advance(1);
    await f.tick();
    assert.equal(f.calls(), 1);
    await f.tick();
    assert.equal(f.calls(), 1);
    f.advance(3_600_000);
    await f.tick();
    assert.equal(f.calls(), 2);
});
test('hidden tabs wait; returning to an overdue tab refreshes immediately', async () => {
    const f = fixture();
    f.document.hidden = true;
    f.advance(4_000_000);
    await f.tick();
    assert.equal(f.calls(), 0);
    f.document.hidden = false;
    await f.listeners.visibilitychange();
    assert.equal(f.calls(), 1);
    await f.listeners.visibilitychange();
    assert.equal(f.calls(), 1);
});

test('a page opened in a hidden tab still catches up when it becomes visible', async () => {
    const f = fixture(true);
    f.advance(7_200_000);
    f.document.hidden = false;
    await f.listeners.visibilitychange();
    assert.equal(f.calls(), 1);
});
