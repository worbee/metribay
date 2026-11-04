# Demo data

A fictional sample portfolio for screenshots, the demo video, a later public read-only demo and test fixtures. Everything is synthetic: fictional brands on reserved `.example` domains, `github.example` / `dev.azure.example` links, made-up numbers. Never mix real project data into it.

Code: `src/Demo/` (generator), `src/Services/DemoData.php` (storage), `src/Console/DemoCommand.php` (`metribay:demo`), `tests/Feature/DemoDataTest.php`.

## Generate or reset

From `core/`:

```sh
scripts/demo-database.sh
```

This rebuilds the ignored SQLite file `standalone/database/demo.sqlite` from scratch (about 10 seconds): `migrate:fresh`, a sample owner, the workspace **Sample portfolio** and seven projects with 400 days of history up to today. Run it again whenever the dates should move to today; health states (for example "Last sync") age from the moment of generation, so regenerate right before taking screenshots. Extra arguments are passed on, for example `scripts/demo-database.sh --seed=7 --days=200`.

Sign in with `demo@metribay.example` / `sample-portfolio-only`. That account exists only in the demo database; `metribay:demo` refuses to run in production. For a public, read-only copy with automatic sign-in, see [DEMO.md](DEMO.md).

The script runs `php artisan metribay:demo --fresh` with `DB_DATABASE` pointing at the demo file and `METRIBAY_COLLECTION_ENABLED=false`. `--fresh` refuses any database that is not SQLite or whose file name does not end in `demo.sqlite`, and refuses while collection is switched on.

To add the samples to an existing installation instead (for example a local Personal install), run `php artisan metribay:demo --email=you@example.com`. Projects are then named "Tallybird · Sample" with `sample-` slugs, and running the command again replaces them. Their data sources stay paused unless collection is switched off for that installation.

## Serve the standalone host on it

Workspace launch configuration `core-demo` (`.claude/launch.json`, port 8006), or by hand from `core/`:

```sh
DB_CONNECTION=sqlite DB_DATABASE="$PWD/standalone/database/demo.sqlite" METRIBAY_COLLECTION_ENABLED=false METRIBAY_ANALYTICS_ENABLED=false QUEUE_CONNECTION=sync php standalone/artisan serve --host=127.0.0.1 --port=8006 --no-reload
```

`METRIBAY_COLLECTION_ENABLED=false` switches collection off for the whole installation: `metribay:sync` dispatches nothing and queued collection jobs return without contacting a provider. Only then are sample sources active, so they look like healthy live sources; with collection on they are paused, because without a connected account they would fail (or, for public providers such as WordPress.org, request fictional names). `METRIBAY_ANALYTICS_ENABLED=false` keeps the consent banner out of screenshots. Project favicons are looked up once per domain and cached; `.example` names do not resolve.

## Screenshots

With the demo host running:

```sh
node scripts/capture-demo.mjs
```

Signs in, then saves `{locale}/{page}-{theme}.png` under `site/src/assets/app-screenshots/` for all twelve app languages in light and dark: the overview, three project pages (Tallybird, Switchback Journal, Alder & Finch, 90 days, full page) and the Tallybird events page, at 1440×900 with device scale 2 (about 84 MB in total). It switches the sample owner's saved language directly in the demo database and resets it to English. Options: `--locales=en,de`, `--themes=dark`, `--out=…`, `--base=…`. Playwright 1.58.2 is a dev dependency of Core (`npm install`); it uses the Chromium that Playwright installs (`npx playwright install chromium` once). The site should caption these as sample data. The originals are git-ignored in the site repository and must not be deleted; site pages, docs and videos start from them and commit only resized, compressed copies under `site/src/assets/screens/` (site/AGENTS.md).

## Use as a test fixture

```php
app(DemoData::class)->populate($workspace, seed: 2026, now: CarbonImmutable::parse('2026-11-03 12:00', 'UTC'), days: 100);
```

`populate()` stores the portfolio in any workspace through `MetricStore::persist()`, the same path real collection uses, so stored rows pass the same validation. Pass `labelled: false` for plain names. `new SamplePortfolio($seed, $now, $days)` returns the same data without a database (`SampleProject` / `SampleSource` objects holding `CollectionResult`s), which is handy for unit-style tests. The same seed and the same `now` always give identical data; another seed gives other noise around the same story. Tests run on SQLite and PostgreSQL.

## The portfolio

One project per audience Metribay is built for. Story beats are placed in "days ago", so they always sit at the same distance from today; events and the effects they explain share the same numbers in `SamplePortfolio.php`.

| Project | Audience | Data sources | Story |
| --- | --- | --- | --- |
| Tallybird (`tallybird.example`) | Indie maker SaaS | GA4 (Europe/Berlin), Search Console, GitHub with production deployments, UptimeRobot, page speed (mobile, page-level field data) | Product Hunt launch 74 days ago (decaying spike, higher plateau after); monthly newsletter bumps; a 34-minute outage 23 days ago; new marketing site 31 days ago slows the lab score until the chat widget loads on click 12 days ago; an upcoming promotion |
| Quillform (`quillform.example`) | WordPress plugin developer | WordPress.org, GitHub releases, Search Console, Umami, UptimeRobot | 17 releases; downloads jump for about a week after each (4.0.0 and the quick 4.0.1 patch most); WordCamp talk 58 days ago; one day WordPress.org did not report |
| Driftwood (`driftwood.example`) | Open-source maintainer | GitHub (busy, squash merges, about 175 open issues, prereleases), Umami docs, Search Console, Bing | 4.0 launch 206 days ago; newsletter feature 39 days ago; conference talk; developer audience quiet at weekends |
| Alder & Finch (`alderandfinch.example`) | Agency client site | GA4 (America/Los_Angeles), Search Console (local and seasonal queries), UptimeRobot, page speed (mobile) | Weekend-heavy bakery traffic with a December peak; spring and autumn campaigns; an unexplained bot spike 19 days ago; a 68-minute outage, then a hosting move 45 days ago halves response time; a hero video 40 days ago drops the lab score to the 50s until images are optimised 15 days ago, while real-user LCP trails over its 28-day window |
| Nordlicht Logistics (`nordlicht.example`) | Fractional leader | Azure DevOps (team of five, pipelines, open bugs, Production deployments twice a week), GA4 (Europe/Berlin), UptimeRobot API monitor, page speed (desktop, site-wide field data only) | Flaky tests 17–19 days ago turn pipelines red; a 22-minute API outage 6 days ago; cookie banner 64 days ago adds blocking time |
| Switchback Journal (`switchbackjournal.example`) | Content and SEO publisher | GA4 (America/Denver), Search Console, Bing (indexed pages) | Strong summer season with winter queries rising now; a Google core update 46 days ago costs non-brand queries about half a position and a third of clicks (Bing unaffected); winter gear series 20 days ago |
| Kestrel Photo (`kestrelphoto.example`) | Self-hoster (Umami) | Umami, UptimeRobot, page speed (mobile, no field data), small GitHub site repository | Weekend hobby commits; a forum feature spike; Umami server upgrade 63–64 days ago leaves two days without data; moving to a new VPS 35 days ago halves response time |

## Realism rules

The generator (`SampleSources`) adds provider behaviour and counting noise to the expected levels from the portfolio. Keep these when editing:

- **Seeded and anchored.** Randomness comes from `Noise` (Xoshiro256**), one stream per project and series derived from the seed, so editing one project never changes another. Noise is indexed by days ago; weekdays, holidays and seasons use real dates.
- **Calendar.** Weekday/weekend patterns differ by audience (B2B quiet at weekends, the bakery and the blog busier); Christmas to New Year, Easter and US Thanksgiving dips; yearly seasons; growth through knots with plateaus.
- **Noise that clusters.** Day-to-day wander is an AR(1) process in log space; counts are Poisson.
- **Unknown is not zero.** Tracker outages and missing provider days are absent rows. Today is partial (scaled by the elapsed share of the provider's day); GA4 keeps three days provisional, Search Console ends yesterday and turns final after three days, Bing lags two days.
- **Provider timezones.** Search Console uses America/Los_Angeles; GA4 properties use their own timezones; the rest use UTC.
- **Visitors are non-additive.** Daily visits ≥ visitors and page views per visit stay within 1.8–3 (bot days fall below). Unique visitors for 7/30/90-day windows (and the windows before them) are stored as periods: less than the sum of the days, at least the busiest day.
- **Search.** Site totals are the sum of the top queries plus a long tail of rare (anonymized) queries, so top queries explain 60–76% of clicks. Queries follow Zipf-like weights, have their own position drift and seasons, and clicks follow a position-based click-through curve (brand queries click more). A search update shifts positions, so clicks fall more than impressions. Query windows and monthly archives are built exactly as the Search Console and Bing connectors build them (Bing in Monday-dated weeks).
- **Repositories.** Commits switch between quiet, normal and busy stretches, rise before releases, and are rare at weekends for teams; lines changed are log-normal per commit; open issues are snapshots since the source was connected, and releases close a batch.
- **Downloads.** WordPress.org downloads jump on the day after a release and decay over a week, sized by how many sites update.
- **Uptime.** One snapshot per day since the monitor was connected, collected separately so each has its own trailing 24-hour window; incidents lower that day's uptime and raise response time; occasional one-minute blips.
- **Page speed.** Daily lab tests with Lighthouse-like variance; the score is computed from the metrics with Lighthouse's published log-normal curves and weights. Chrome UX Report field data averages the days of each 28-day window (weekly history before the connection, daily after), so it trails a deploy that the lab shows at once.
- **Plausible ratios.** Organic clicks stay below visits (about two thirds of visits; four fifths for the blog); Bing is roughly a tenth of Google.
