/** Folds case, accents and spacing so "Čeština"-style input matches either spelling. */
export function foldText(text) {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
}

/**
 * Filters the Add data source provider picker. A group title counts as a match, so searching
 * "analytics" keeps that whole group. Returns the number of providers left visible.
 */
export function filterProviders(root, query) {
    const needle = foldText(query);
    let shown = 0;
    root.querySelectorAll('[data-provider-group]').forEach(group => {
        const heading = foldText(group.querySelector('h3')?.textContent ?? '');
        let visible = 0;
        group.querySelectorAll('[data-provider-row]').forEach(row => {
            row.hidden = needle !== '' && !heading.includes(needle) && !foldText(row.textContent).includes(needle);
            if (!row.hidden) visible++;
        });
        group.hidden = visible === 0;
        shown += visible;
    });
    const empty = root.querySelector('[data-provider-empty]');
    if (empty) empty.hidden = shown > 0;

    return shown;
}
