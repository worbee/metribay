// Run against the synthetic demo host from docs/DEMO_DATA.md:
// node scripts/check-overview-layout.mjs [http://127.0.0.1:8006]
// BROWSER=chromium or BROWSER=firefox selects one engine; HEADED=1 opens a window.
import assert from 'node:assert/strict';
import { chromium, firefox } from 'playwright';

const base = process.argv[2] ?? 'http://127.0.0.1:8006';
for (const engine of [chromium, firefox].filter(engine => !process.env.BROWSER || engine.name() === process.env.BROWSER)) {
    console.log(`Checking ${engine.name()}`);
    const browser = await engine.launch({ headless: !process.env.HEADED, timeout: 30000 });
    try {
        const page = await browser.newPage();
        page.setDefaultTimeout(15000);
        await page.goto(`${base}/login`);
        await page.locator('input[type=email]').fill('demo@metribay.example');
        await page.locator('input[type=password]').fill('sample-portfolio-only');
        await Promise.all([page.waitForURL('**/w/**'), page.locator('button[type=submit]').click()]);
        // Exercise the crowded portfolio from the report, including long values.
        await page.evaluate(() => {
            document.querySelectorAll('.overview-table tr').forEach(row => {
                while (row.children.length < 11) row.append(row.children[1].cloneNode(true));
            });
            document.querySelectorAll('.overview-table .cell-value').forEach(value => { value.textContent = '123,456'; });
        });
        for (const theme of ['light', 'dark']) {
            for (const density of ['comfortable', 'compact']) {
                await page.evaluate(({ theme, density }) => {
                    document.documentElement.dataset.theme = theme;
                    document.documentElement.dataset.density = density;
                }, { theme, density });
                for (const width of [320, 390, 760, 761, 1024, 1370, 1440, 1920, 2560]) {
                    await page.setViewportSize({ width, height: 1000 });
                    const layout = await page.evaluate(() => {
                        const outside = (inner, outer) => inner.left < outer.left - 1 || inner.right > outer.right + 1;
                        const overflow = [...document.querySelectorAll('.overview-table .overview-cell')].flatMap(cell => {
                            const bounds = cell.closest('td').getBoundingClientRect();
                            return [...cell.querySelectorAll('strong, .cell-secondary, .cell-change, svg')]
                                .filter(child => child.getBoundingClientRect().width && outside(child.getBoundingClientRect(), bounds))
                                .map(child => child.classList.toString());
                        });
                        return { overflow, pageWidth: document.documentElement.scrollWidth, mainWidth: document.querySelector('.app-main').getBoundingClientRect().width };
                    });
                    assert.deepEqual(layout.overflow, [], `${engine.name()} ${width}: metric/chart exceeds its cell`);
                    assert.ok(layout.pageWidth <= width, `${engine.name()} ${width}: document scrolls horizontally`);
                    if (width >= 1920) assert.equal(layout.mainWidth, 1920, 'shared app container uses Full HD width');
                    console.log(`${engine.name()} ${theme} ${density} ${width}px: passed`);
                }
            }
        }
        const project = await page.locator('.project-link').first().getAttribute('href');
        await page.goto(project);
        for (const width of [320, 390, 761, 1370, 1920, 2560]) {
            await page.setViewportSize({ width, height: 1000 });
            const layout = await page.evaluate(() => ({
                mainWidth: document.querySelector('.app-main').getBoundingClientRect().width,
            }));
            assert.equal(layout.mainWidth, Math.min(width, 1920), 'project detail shares the app width');
            console.log(`${engine.name()} project ${width}px: passed`);
        }
    } finally {
        await browser.close();
    }
}
