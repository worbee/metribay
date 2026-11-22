import assert from 'node:assert/strict';
import { test } from 'node:test';
import { filterProviders, foldText } from '../../resources/js/provider-filter.js';

function element(textContent, children = {}) {
    return { textContent, hidden: false, children, querySelector(selector) { return (children[selector] ?? [])[0] ?? null; }, querySelectorAll(selector) { return children[selector] ?? []; } };
}

function picker() {
    const ga4 = element('Google Analytics 4 Google’s analytics for websites and apps Setup guide');
    const umami = element('Umami Open-source analytics on your own server');
    const gsc = element('Google Search Console Clicks and impressions in Google Search');
    const bluesky = element('Bluesky Followers of a Bluesky account No sign-in needed');
    const analytics = element('', { h3: [element('Website analytics')], '[data-provider-row]': [ga4, umami] });
    const search = element('', { h3: [element('Search engines')], '[data-provider-row]': [gsc] });
    const social = element('', { h3: [element('Sociální sítě')], '[data-provider-row]': [bluesky] });
    const empty = element('No provider matches.');
    empty.hidden = true;
    const root = element('', { '[data-provider-group]': [analytics, search, social], '[data-provider-empty]': [empty] });

    return { root, empty, rows: { ga4, umami, gsc, bluesky }, groups: { analytics, search, social } };
}

test('text folding ignores case, accents and repeated spaces', () => {
    assert.equal(foldText('  Sociální   Sítě '), 'socialni site');
});

test('an empty query shows every provider and group', () => {
    const { root, rows, groups, empty } = picker();
    assert.equal(filterProviders(root, '  '), 4);
    assert.ok(Object.values(rows).every(row => !row.hidden));
    assert.ok(Object.values(groups).every(group => !group.hidden));
    assert.equal(empty.hidden, true);
});

test('a provider match hides other rows and groups without matches', () => {
    const { root, rows, groups } = picker();
    assert.equal(filterProviders(root, 'google'), 2);
    assert.equal(rows.umami.hidden, true);
    assert.equal(rows.gsc.hidden, false);
    assert.equal(groups.social.hidden, true);
});

test('a group title keeps the whole group, including accented titles', () => {
    const { root, rows, groups } = picker();
    assert.equal(filterProviders(root, 'website analytics'), 2);
    assert.equal(rows.umami.hidden, false);
    assert.equal(groups.search.hidden, true);
    assert.equal(filterProviders(root, 'socialni'), 1);
    assert.equal(rows.bluesky.hidden, false);
});

test('no match shows the empty message', () => {
    const { root, empty, groups } = picker();
    assert.equal(filterProviders(root, 'zzz'), 0);
    assert.equal(empty.hidden, false);
    assert.ok(Object.values(groups).every(group => group.hidden));
});
