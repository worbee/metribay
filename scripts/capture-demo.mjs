// Screenshots of the sample portfolio for the website (docs/DEMO_DATA.md).
// Needs the demo host running on the demo database (launch configuration "core-demo", or see the docs), then:
//   node scripts/capture-demo.mjs [--base=http://127.0.0.1:8006] [--out=../site/src/assets/app-screenshots] [--locales=en,de] [--themes=light,dark]
// The script signs in as the sample owner and switches the owner's saved language directly in the demo database.
import { mkdir } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { DatabaseSync } from 'node:sqlite';
import { chromium } from 'playwright';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const options = Object.fromEntries(process.argv.slice(2).map(arg => arg.replace(/^--/, '').split('=')));
const base = (options.base ?? 'http://127.0.0.1:8006').replace(/\/$/, '');
const out = resolve(root, options.out ?? '../site/src/assets/app-screenshots');
const database = resolve(root, options.database ?? 'standalone/database/demo.sqlite');
const locales = (options.locales ?? 'en,bg,cs,de,es,fr,it,nl,pl,sk,sl,sr').split(',');
const themes = (options.themes ?? 'light,dark').split(',');
const email = 'demo@metribay.example';
const password = 'sample-portfolio-only';

// [file name, project name or null for the overview, path after the project URL, full page]
const shots = [
    ['overview', null, '', false],
    ['project-tallybird', 'Tallybird', '?days=90', true],
    ['project-switchback', 'Switchback Journal', '?days=90', true],
    ['project-alder-and-finch', 'Alder & Finch', '?days=90', true],
    ['events-tallybird', 'Tallybird', '/events', false],
];

const db = new DatabaseSync(database);
const setLocale = db.prepare('update users set locale = ? where email = ?');
const browser = await chromium.launch();
try {
    for (const theme of themes) {
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: theme, reducedMotion: 'reduce' });
        const page = await context.newPage();
        await page.goto(`${base}/login`);
        await page.getByLabel(/email/i).fill(email);
        await page.locator('input[type=password]').fill(password);
        await Promise.all([page.waitForURL(url => !url.pathname.startsWith('/login')), page.locator('form button[type=submit]').first().click()]);
        const overview = page.url().replace(/[?#].*$/, '');
        const projects = Object.fromEntries(await page.locator('a[href*="/projects/"]').evaluateAll(links => links
            .map(link => [link.textContent.trim().split('\n')[0].trim(), link.href.replace(/\?.*$/, '')])
            .filter(([, href]) => /\/projects\/\d+$/.test(href))));
        for (const locale of locales) {
            setLocale.run(locale, email);
            await mkdir(`${out}/${locale}`, { recursive: true });
            for (const [name, project, path, fullPage] of shots) {
                const url = project ? Object.entries(projects).find(([text]) => text.startsWith(project))?.[1] : overview;
                if (!url) throw new Error(`Project link not found: ${project}`);
                await page.goto(url + path, { waitUntil: 'networkidle' });
                await page.evaluate(() => document.fonts.ready);
                await page.mouse.move(0, 0); // No hover highlight.
                await page.waitForTimeout(400); // Charts draw after their data arrives.
                const file = `${out}/${locale}/${name}-${theme}.png`;
                await page.screenshot({ path: file, fullPage });
                console.log(file);
            }
        }
        await context.close();
    }
} finally {
    setLocale.run('en', email);
    await browser.close();
}
