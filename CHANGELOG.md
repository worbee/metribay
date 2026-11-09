# Changelog

## Unreleased

- Open a project's **Overview** for recent uptime incidents and significant drops in Google Search Console clicks. **All metrics** opens by default. Fold chart groups to focus on the ones you need. Your browser remembers which groups you folded for each project.
- The project reporting controls and wide tables fit narrow screens.

## 1.0.0 — 2026-11-09

- Metribay 1.0.0, the first stable release. The code is the same as 0.17.1, after the 0.17 Google reconnect and account security work. No database migration or configuration change: update an existing 0.17.1 install by pulling the new image or running `composer update metribay/core`.
- Docker images are tagged `1.0.0`, `1.0` and `latest`.

## 0.17.1 — 2026-11-07

- Require the current password when changing the sign-in email. Name and language changes remain available without it. The profile form explains this in all twelve languages.
- Apply password-session validation to all web routes, including Fortify account settings and MFA endpoints. Sessions invalidated by a password change or reset must sign in again.
- Restrict production standalone requests to the hostname in `APP_URL`, protecting password-reset and verification links. Docker healthchecks send the configured hostname; self-hosters should check `APP_URL` before updating. No database migration.
- Include the accepted wider dashboard layout and local test improvements prepared after 0.17.0.

## 0.17.0 — 2026-11-07

- **Reconnect Google** on Google Analytics and Search Console accounts in **Settings → Connected accounts**, when browser sign-in with Google is configured. It signs in with Google again and replaces the saved authorization on the same account, so its data sources keep the account and their history and collect again right away. Before saving, it checks that the new sign-in can read every site or property those data sources use. If any is missing, the saved authorization stays as it was. Accounts that a host connected through its own Google app show only the name field and **Reconnect Google** instead of credential fields.
- When Google rejects a saved authorization (`invalid_grant`: the refresh token expired or was revoked, or the key was disabled), collection fails with `GoogleAuthorizationExpired` instead of the generic `ConnectorException`. The project page explains it and links to the account. Project settings show "Google access expired or was revoked".
- Google accounts connected through the browser are now named `GSC · …` or `GA4 · …` instead of `Google · …`. Existing names are unchanged.
- New strings in all twelve languages. No migration.

## 0.16.1 — 2026-11-06

- Apply Symfony security fixes and refresh Alpine packages in the self-hosted Docker image. No database migration.

## 0.16.0 — 2026-11-05

- **RSS / Atom** and **Sitemap** data sources, in a new Content group. One RSS / Atom source reads both formats. A sitemap may be a urlset or an index of urlsets. **Items published** counts dated feed entries and sitemap URLs by `lastmod`. **Published URLs** is the current size of a sitemap. Each published item is also an event. **Automatically convert these values into events** is on unless you turn it off, including for GitHub release events. Deployments stay on the environments setting. No account is required. New strings in all twelve languages. No migration.

## 0.15.0 — 2026-11-04

- Connect Paddle Billing to see net revenue, MRR and active paying subscribers. The metrics-only key keeps individual customer data in Paddle.
- **Plausible and Matomo** data sources, on the same path as Umami. Plausible takes a public HTTPS server URL (`https://plausible.io` for Cloud) and a team Stats API key, then the site domain. Matomo takes the server URL and an auth token (`token_auth` in the POST body), then the numeric site ID. Both follow the site's reporting timezone. Plausible stores exact period visitors. Matomo does not, because a custom range has no true unique visitors. New strings in all twelve languages. No migration.
- **GitHub releases** data source. It stores **Release downloads**: the current sum of `download_count` on assets of non-draft releases, including prereleases. GitHub's automatic source archives are not included. Each sync records one UTC snapshot and replaces that day's value. A public repository needs no account. A personal access token is optional, for a private repository or a higher rate limit. This source does not use a GitHub App. New strings in all twelve languages. No migration.

## 0.14.0 — 2026-11-04

- **Demo mode** for a public, read-only Metribay ([docs/DEMO.md](docs/DEMO.md)), running at https://demo.metribay.com. `METRIBAY_DEMO=true` signs visitors in as the sample owner, refuses every change with "Not available in the demo." before it reaches a controller, keeps the language in the session and shows a banner with trial and self-hosting links. `DB_READ_ONLY=true` opens SQLite read-only. The Docker `demo` role now serves read-only with automatic sign-in, and the new `demo-refresh` role rebuilds and swaps the database without downtime. Usage analytics count the demo as its own `demo` edition when enabled.
- Core's web middleware (language, abandoned setup, demo) is registered through the HTTP kernel, so it also applies when the kernel is built after Core boots, as in tests.
- New strings in all twelve languages. No migration.

## 0.13.0 — 2026-11-04

- **Delete a project.** Project settings end with a **Delete project** section that warns that all historic data goes with it. The workspace owner confirms, and the project, its data sources, events, hidden charts, metric history, top queries and monthly query archive are deleted (`DELETE /w/{workspace}/projects/{project}`, `metribay.projects.destroy`). Connected accounts stay. Editors see who can delete; viewers see nothing.
- **Export metric history.** **Settings → Import & export** gains **Export metric history** with **Download CSV** (`GET /w/{workspace}/export/history`): one row per daily or period value for every project, data source and metric (`project, domain, data_source, provider, metric, start_date, end_date, value, partial`). Cells that a spreadsheet would read as a formula are prefixed with an apostrophe. Owners and editors can export.
- New `Services\DataDeletion` deletes one project (`project()`) or everything in a workspace (`workspace()`: projects, data, connected accounts and groups; the workspace and its members stay), deleting rows explicitly so the result does not depend on cascading foreign keys. Hosts can use it for retention policies. New strings in all twelve languages. No migration.

## 0.12.0 — 2026-11-04

First public release.

- **Docker image** `ghcr.io/worbee/metribay` for linux/amd64 and linux/arm64: one FrankenPHP image with `web`, `worker`, `scheduler`, `demo` and `key` roles, compose files for SQLite and PostgreSQL ([docs/DOCKER.md](docs/DOCKER.md)). The standalone host can trust a reverse proxy (`METRIBAY_TRUSTED_PROXIES`) and set the SQLite busy timeout and journal mode from the environment.
- README for self-hosters, contributing, security and conduct guidelines, issue and pull request templates.
- **Usage analytics are off by default in self-hosted installs.** Without `METRIBAY_ANALYTICS_ENABLED`, the personal edition shows no consent prompt and loads no analytics; Cloud and Desktop keep asking. `UsageAnalytics::enabled()` resolves the default. Set `true` to ask users, `false` to turn it off anywhere.
- **Realistic sample portfolio.** `metribay:demo` now generates seven fictional projects (one per audience, `.example` domains) with 400 days of seeded, deterministic data for every provider metric: seasons and holidays, launch spikes, a search update, bot spikes, missing and provisional days, unique-visitor periods, top queries and monthly archives, release-driven downloads, bursty commits, uptime incidents, page speed that moves after deploys, and matching events. `--fresh` rebuilds a dedicated `demo.sqlite`; `scripts/demo-database.sh` and `scripts/capture-demo.mjs` (Playwright, dev dependency) produce the database and screenshots. See [Demo data](docs/DEMO_DATA.md).
- Overview: project names and domains are no longer cut short. "View project details" takes no room until a row is hovered or focused, the project column keeps at least 190 px, and long translated column headers wrap.
- `METRIBAY_COLLECTION_ENABLED=false` switches collection off for an installation: `metribay:sync` dispatches nothing and collection jobs return without contacting providers. Default on; no migration.

## 0.11.1 — 2026-11-03

- Show a muted count beside providers already used by the current project in Add data source. Counts represent project data sources, including disabled sources, rather than connected accounts; zero counts are omitted. Helper text is translated into all twelve languages.

## 0.11.0 — 2026-11-03

- Add optional, consent-gated Umami usage measurement with locally bundled CookieConsent, twelve languages, privacy controls and independent Personal/Desktop opt-in. See [Usage analytics](docs/USAGE_ANALYTICS.md).

## 0.10.1 — 2026-11-03

- The project page's **Dates…** picker closes when you click elsewhere, move focus away or press Esc (Esc returns focus to **Dates…**), like the profile menu. No migration.

## 0.10.0 — 2026-11-03

- **Monthly top queries archive.** Search Console and Bing data sources now keep each complete calendar month of top queries for good (Search Console: top 1,000 per month, backfilled up to 16 months, four months per sync; Bing: its weekly rows grouped by month). The **Top queries** block gets a menu beside its heading to show an archived month instead of the current window, compared with the month before or any other archived month (**vs August 2026**, **No comparison**). URLs keep the choice (`?queries=2026-10&compare=2026-06`). Additive migration `search_query_months` / `search_query_month_rows`; archived months move with their project.
- **Yesterday and custom ranges.** The overview menu and the project page's period switch add **Yesterday** (the last complete day, as number tiles) and a custom range of up to 731 calendar dates (`?from=…&to=…`, **Custom range…** / **Dates…**). Each compares with the same number of days before. Links keep the period; health always reflects today. Unique visitors use the day's value for Yesterday and are unavailable for custom ranges; Top queries show the closest stored window and say so, or the archived month a custom range exactly covers. New `Metrics\Period`; `DashboardData::projects()` and `SearchQueries::forSources()` accept a Period (an int still means the last N days).
- **Snapshot changes and uptime sparkline.** Snapshot metrics (page speed, open issues, indexed pages, uptime, response time) show the difference from the previous period's last value in their own unit (`+12`, `−500 ms`, `+0.40 pp`), coloured by polarity. The overview's uptime column gets a sparkline of the lowest daily uptime. Sparklines with a single point are no longer drawn.
- Available in all twelve interface languages.

## 0.9.2 — 2026-11-03

- Link the project header’s attention and delayed counts to the affected monitor status or source warning. When several sources need attention, named links reach each one. Highlight the target, and keep source warnings reachable when all charts are hidden. No migration.

## 0.9.1 — 2026-11-03

- Test fix only: the events link assertion no longer depends on the host's `APP_URL`. No application change.

## 0.9.0 — 2026-11-03

- **Top queries** for Search Console and Bing data sources: the project page's Search group ends with a block listing **Clicked**, **Ranked** and **Opportunities** (positions 4–20, at least 10 impressions, click-through rate below the site's average) with clicks, impressions, CTR and average position, each compared with the window before; **New** marks queries that were not there before. Ten rows per list with **Show all**, a Google/Bing switch, the provider's window in the caption (Bing is weekly), a note when Google left out rare queries, and a hide button like a chart (`top_queries`). Search Console collects six query reports per sync (7, 30 and 90 days and the window before each); Bing sums its weekly `GetQueryStats`. Migration `search_queries` (additive; projects moved to another workspace keep their queries).
- **Events page** per project (`/w/{workspace}/projects/{project}/events`, docs/EVENTS_PAGE.md): a context strip with event lanes (Releases, Deployments, Custom) above the period's main line, **Upcoming** with future events and each repeating series once, and a timeline grouped by month and day in which same-day imported deployments collapse into one row ("4 deployments · production"). Filters by type, data source and title, periods 7/30/90 days and **All**, paging, quick add at the top and edit dialogs instead of inline fold-outs.
- The project page no longer lists events. Its header gets an **Events** link with the number of events in the period, group legends link to the events page, and clicking a chart marker (or Enter on a focused chart) opens that event or day there. Available in all twelve interface languages.

## 0.8.0 — 2026-11-03

- Redesign the project page (docs/PROJECT_PAGE.md). A compact header with the favicon, domain link, a status line (data sources, attention/delayed/importing/paused counts, last sync), a 7/30/90-day switch and secondary **Add event** / **Add data source** buttons. The project's overview row follows as a summary that links to its groups.
- Charts are grouped by meaning in overview order (**Audience**, **Search**, **Development**, **Distribution**, **Reliability**, **Page speed**) instead of per data source. Each group has one large chart with the previous-period total in words, small charts beside it, number tiles for open issues and a daily status strip for monitor status; one-chart groups share a row. Several sources of the same metric get labelled charts. Charts without data collapse to one line; failed, delayed and importing sources show a note in their group.
- Changes are coloured by polarity (a rise in response time, failed pipeline runs, open issues or page speed timings is red; lines changed is neutral), matching the overview's arrows and rounding. Page speed values show Good / Needs improvement / Poor from the published thresholds.
- Charts: dashed segment and hollow point for incomplete dates, a date line shared across all charts on hover, one legend per group, quieter **Daily values** tables (newest first) and coverage shown only when incomplete. New `Metrics\ProjectLayout`; `partials/overview-change` takes an optional `polarity` and its classes are now `is-good` / `is-bad`. Available in all twelve interface languages. No migration.

## 0.7.0 — 2026-11-02

- Add **Azure DevOps** as a data source: connect an organization with an organization-scoped personal access token (Code, Build, Work Items and Project and Team set to Read), then pick a Git repository from any project. Collects **Commits** on the default branch, **PRs merged** from completed pull requests, the new **Pipeline runs succeeded** and **Pipeline runs failed** for this repository's default-branch runs, and **Open issues** from open bug work items (Issue in the Basic process). Lines changed is not collected.
- Optional deployment environments turn successful Azure DevOps deployments of default-branch or tag runs into events, one per run and environment. They need the token's Environment (Read & manage) scope; without it the source explains the missing scope (`AzureDevOpsDeploymentAccess`). Environment suggestions, filter changes and errors now share one path with GitHub (`DeploymentAccess`, `ListsDeploymentEnvironments`, `ConnectorRegistry::environments()`). Available in all twelve interface languages. No migration.

## 0.6.0 — 2026-11-02

- Move a project to another workspace from the new **Workspace** section in Project settings, with **Manage workspaces** and (where the host allows it) **New workspace** links. The section appears only when the host exposes workspaces (the `metribay.workspaces` route), so single-workspace editions are unchanged. Moving needs `manage_access` on the current workspace and `edit` on the target, and checks the project version.
- `ProjectTransfer` moves the project with its data sources, events, hidden charts, daily and period metrics and sync runs in one transaction. Composite workspace keys cannot change in place, so the project and its sources are recreated in the target workspace and get new IDs; the group is cleared and slug or account-name conflicts get a numeric suffix. Connected accounts used only by the moved project move with it when the user can manage accounts in both workspaces (credentials are re-bound, never copied); other data sources keep their settings and history without an account. New route `metribay.projects.move`. Available in all twelve interface languages. No migration.

## 0.5.0 — 2026-11-02

- Refresh the overview and project charts hourly without a page reload, catching up when an overdue tab becomes visible. Keep the reporting period, chart legend choices and open event forms. This replaces the overview's 15-second polling and the first-import page reload.
- Show cached domain favicons beside project names in the overview. Fetch bounded public HTTPS icons (raster or sandboxed SVG) through the app, with a neutral fallback when no usable icon is available.
- Gray out and disable resources already added to the current project in all provider pickers. Reject duplicate selections and manual entries before confirmation, and recheck under the project lock when saving.

## 0.4.1 — 2026-11-02

- Turn each GitHub Actions workflow run into one deployment event per environment instead of one per job and re-run. The event is dated by the run's last success and links to the run; a run whose latest outcome was a failure is skipped. Deployments outside Actions stay one event each, linking to the commit.
- Import only deployments from the repository's default branch or a tag. Deployments from other branches, or whose branch was deleted, are skipped: for example, jobs that only use the production environment's secrets on pull requests. Existing deployment events are replaced at the next sync. No migration or new strings.

## Unreleased

- Remove **Sync now** from the project page and Project settings, together with the `metribay.sources.sync` route. Data sources are collected on the schedule only (and after setup or a change of GitHub deployment environments). `php artisan metribay:sync` remains for operators. Hosts that referenced the route name, such as Cloud's refresh limits, can drop it once they consume this release.

## 0.4.0 — 2026-11-02

- Import successful GitHub deployments as project events. Each GitHub data source can name up to ten deployment environments (for example `production`) during setup or under **Manage data source**; **Find environments** lists the environments of the repository's latest deployments, and setup suggests `production` when it exists. Events are dated by the first success status, titled with the environment and tag or short commit, and link to the commit. Deployments later replaced by newer ones still count. Changing the environments removes existing deployment events and queues a fresh import. The event list labels imported events as **Release** or **Deployment**. Available in all twelve interface languages.
- Read GitHub releases only for the collection window (plus 30 days of creation dates, so drafts published later are still found) instead of the whole release history.
- Reconcile automatic events per origin and only inside the window that was read: older imported events stay, and a provider can no longer delete events outside what it fetched. `CollectionResult::$authoritativeEvents` is now a map of origin to window start, and each event row names its `origin` (validated per provider by `ProviderCatalog::eventOrigins()`).
- Reading deployments needs read access to deployments: a classic token with `repo` scope, a fine-grained token or GitHub App with **Deployments: Read-only**. Without it, sources with deployment environments fail with a clear message (`GitHubDeploymentAccess`) and sources without environments are unaffected. GitHub App installation tokens request the deployments permission only when environments are chosen.
- Deployment environments are settings, not part of a data source's identity. No migration.

## 0.3.2 — 2026-11-02

- Let editors hide individual charts from a project page for everyone in the workspace. The hide button shows on hover, on keyboard focus, and always on touch screens. Hiding happens straight away and offers **Undo** where the chart was. A quiet "N charts hidden · Show" line links to the new **Hidden charts** section in Project settings, where charts can be shown again. The overview, collection and stored metrics are unchanged. Available in all twelve interface languages.
- Add the `hidden_charts` table (project, data source, metric key; removed with its project or data source). Rolling back needs `migrate:rollback --step=1` first.

## 0.3.1 — 2026-11-02

- Compact the signed-in header to one row and move navigation into the profile menu. Keep the last accessible workspace’s links available on account pages, rechecking access; hosts without account controls retain an Overview/Settings menu.
- Replace the overview’s repeated title and description with a short Projects heading, keep period/search/add/health controls, visibly label totals across all projects, and add metric column dividers.

## 0.3.0 — 2026-11-02

- Add Page speed sources: URL/device setup, daily Lighthouse score/LCP/CLS/TBT snapshots, separate CrUX page/site p75 LCP/INP/CLS windows and up to40 weeks of available history. Own-key connections remain in Core; a host contract supports managed Cloud keys.
- Show the lowest lab score per project in the overview, without summing scores across sources or projects. Preserve missing data, exact field windows, lab metadata and daily cache/queue behavior; add ratio and score formatting and all twelve interface languages.
- Add nullable `daily_metrics.context` JSON metadata. Retain the additive column on host rollback and pause Page speed sources before running an earlier Core.

## 0.2.5 — 2026-11-02

- Add a sortable **Open issues** column to the project overview, with a daily sparkline, repository breakdown and totals across projects. It uses the existing GitHub issues-only snapshots and sums each repository's latest count, rather than adding counts across days. Snapshot metrics have no percentage change; missing observations stay unknown, and combined snapshot charts require every source to have reported that day.
- Match overview columns by metric capability so future providers can supply open issues without changing the dashboard. The project chart now explains that snapshots accumulate from collection and cannot be backfilled from GitHub's current count, in all twelve interface languages.

## 0.2.4 — 2026-11-02

- Guests arriving with `?lang={code}` (one of `metribay.locales`, such as from the marketing site) get that language for the rest of the session, ahead of the browser's preference. A signed-in user's saved language always wins and the parameter does not change it.

## 0.2.3 — 2026-11-01

- Add interface translations for Bulgarian (`bg`), Serbian Latin (`sr`), Polish (`pl`), Spanish (`es`), Italian (`it`), French (`fr`), Dutch (`nl`), Czech (`cs`), Slovenian (`sl`) and Slovak (`sk`), including language-selector entries and Laravel auth/validation/pagination/password files. English remains the fallback for missing strings.
- Move **Add event** on the project page to a secondary button beside **Add data source**; the Events section stays at the bottom.

## 0.2.2 — 2026-10-31

- Make the project overview compact: one fixed-height row per project with sortable columns for visits, search clicks, commits and merged PRs, downloads and uptime. Several data sources of one kind are added together (never unique visitors), with the change against the previous period, a small trend line and a per-source breakdown on hover. Projects with a failed or delayed data source are highlighted and listed first; a totals row covers all projects, and the whole row opens the project page. `DashboardData` now returns a per-project `summary` and `status`.

## 0.2.1 — 2026-10-31

- When a workspace already has a connected account for the provider, the setup account step offers it first; connecting another account (browser authorization or own credentials) moves below as a secondary option. Host account views can use `$connectButtonClass` for the same styling.

## 0.2.0 — 2026-10-30

- Rename Annotations to Events throughout (model `ProjectEvent`, table `project_events`, routes `metribay.events.*`). Custom events can repeat weekly, monthly or yearly and use one of four colors; imported GitHub releases are shown as distinct, read-only automatic events. Events appear as markers on project charts.
- Make projects the starting point: the overview offers Add project, and data sources are always added from a project. Each Add data source click starts a fresh setup; leaving discards unfinished form state and the Save for later link is gone. Saving an account remains an explicit step.
- Rename user-facing terms: connections are now connected accounts, sources are data sources. Settings show Projects, Connected accounts (with usage and removal of unused accounts) and Import & export; a new project settings page manages each project's data sources. Older `tab=sources` and `tab=connections` links redirect.
- Rename the personal Account page to Profile at `/profile`; `/account` redirects.
- Add English and German interface translations, a language selector on the profile and sign-in pages, locale-aware numbers and dates, and per-user language for notifications. New migration adds `users.locale`.
- Replace the project index hover arrow with a “View project details” label.

## 0.1.9 — 2026-10-30

- Require PHP 8.5 or newer; CI now tests only PHP 8.5 on SQLite and PostgreSQL.

## 0.1.8 — 2026-10-30

- Discover Umami websites shared through teams as well as personal websites.
- Show actionable Umami sign-in, access, connection and response errors during source setup and refresh.

## 0.1.7 — 2026-10-30

- Let hosts add managed provider actions to account and resource setup without replacing the shared flow.
- Allow hosts to extend resource discovery while retaining built-in bring-your-own credentials.

## 0.1.5 — 2026-10-29

- Keep own credentials and developer-app configuration in Core; let hosts own managed authorization and runtime credentials.
- Apply host request policies to every provider request, including discovery and token exchange.
- Show actionable request-limit messages and reject managed service references in standalone installations.

## 0.1.4 — 2026-10-29

- Keep workspace selection and management in Cloud; Personal opens its dashboard directly.
- Provide host view and settings hooks while preserving internal data scopes and existing data.

## 0.1.3 — 2026-10-29

- Let hosts disable account UI and its page while keeping shared appearance controls in Settings.


## 0.1.2 — 2026-10-28

- Guide source setup through provider, account, resource selection and project confirmation.
- Discover provider resources, infer labels/timezones and queue the first import automatically.
- Support configured Google browser authorization with a shared callback.
- Resume workspace setup and display import progress without misleading empty charts.
- Remove groups from everyday navigation while preserving existing configuration data.
- Separate project/account creation views, support workspace renaming, and expose a shared settings template for hosts.
