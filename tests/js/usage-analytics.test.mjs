import test from 'node:test';
import assert from 'node:assert/strict';
import { analyticsAllowed, consentCookieName, usagePayload } from '../../resources/js/usage-payload.js';

const cookie = categories => `other=x; cc_cookie=${encodeURIComponent(JSON.stringify({ revision: 1, categories }))}`;
const config = { edition: 'cloud', page: 'project', website: 'synthetic-id', url: '/w/private/projects/51?token=secret', title: 'Private project', referrer: 'https://private.example', user: 'secret@example.com' };

test('analytics requires explicit current consent; missing, corrupt, necessary-only and old revisions fail closed', () => {
    for (const raw of ['', 'cc_cookie=broken', cookie(['necessary']), 'cc_cookie=' + encodeURIComponent(JSON.stringify({ revision: 0, categories: ['analytics'] }))]) assert.equal(analyticsAllowed(raw, 'cc_cookie'), false);
    assert.equal(analyticsAllowed(cookie(['necessary', 'analytics']), 'cc_cookie'), true);
    assert.equal(analyticsAllowed(cookie(['necessary']), 'cc_cookie'), false);
});

test('usage payload contains only coarse route data and no original browser or project data', () => {
    assert.deepEqual(usagePayload(config), { website: 'synthetic-id', hostname: 'app.metribay.com', url: '/cloud/project', title: 'Metribay cloud: project', referrer: '' });
    assert.equal(usagePayload({ ...config, page: 'operator' }), null);
    assert.equal(usagePayload(config, 'unapproved'), null);
    assert.equal(usagePayload(config, 'signup_completed').name, 'signup_completed');
});

test('Personal and Desktop have independent local consent and never disclose local hostnames', () => {
    for (const edition of ['personal', 'desktop']) {
        const name = consentCookieName(edition);
        assert.notEqual(name, 'cc_cookie');
        assert.equal(analyticsAllowed(cookie(['analytics']), name), false);
        assert.equal(usagePayload({ ...config, edition }).hostname, `metribay.${edition}`);
    }
    assert.notEqual(consentCookieName('personal'), consentCookieName('desktop'));
});

import { createUsageTracker } from '../../resources/js/usage-tracker.js';

test('tracker makes no request before acceptance, loads once, and emits only permitted page/conversion', () => {
    let consent = false;
    let loads = 0;
    let finish;
    const sent = [];
    const tracker = createUsageTracker({ ...config, event: 'signup_completed' }, {
        allowed: () => consent,
        appendScript: onload => { loads++; finish = onload; },
        track: payload => sent.push(payload),
    });
    tracker.enable(); assert.equal(loads, 0);
    consent = true; tracker.enable(); tracker.enable(); assert.equal(loads, 1);
    finish(); finish(); assert.equal(sent.length, 2);
    assert.equal(sent[1].name, 'signup_completed');
    assert.equal(tracker.beforeSend('identify', config), false);
    assert.deepEqual(tracker.beforeSend('event', config), usagePayload(config));
    consent = false;
    assert.equal(tracker.beforeSend('event', config), false);
});

test('withdrawal while tracker is loading prevents all measurement and a failed load can retry', () => {
    let consent = true;
    let finish;
    let fail;
    const sent = [];
    const tracker = createUsageTracker(config, {
        allowed: () => consent,
        appendScript: (onload, onerror) => { finish = onload; fail = onerror; },
        track: payload => sent.push(payload),
    });
    tracker.enable(); fail(); assert.equal(tracker.started, false);
    tracker.enable(); consent = false; finish(); assert.equal(sent.length, 0);
    assert.equal(tracker.beforeSend('event', config), false);
});
