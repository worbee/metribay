# Changelog

## Unreleased

## 1.9.0 — 2026-11-28

- Hosts can list data sources they run for a project themselves, without a connected account, through the new `ManagedSources` contract. The project header counts them with the other data sources, and their problems link to where they show on the page. **Project settings** lists them under **Data sources** with their status and a link to their own settings. **Add data source** offers them in their provider group. Standalone Core has none.
- The host's Search content (`metribay.project_search.view`) appears only when the project has a managed source that reports to Search, so a project without one shows no empty section. The `metribay.project_search.settings_view` hook is removed; hosts list the source through `ManagedSources` instead.
- The stacked bar chart can show calendar months, keep a missing value unknown instead of zero and name its total.
- Two new strings in all twelve languages. No migration or configuration change.

## 1.8.0 — 2026-11-27

- New data source: **PrestaShop** (1.7, 8 and 9), in the **Revenue** group. It records **Orders** (valid orders) and **Store revenue** (order totals without tax, converted to the shop's default currency) per day in the shop's timezone. Connect it with the shop's address and a webservice key that may only view orders, currencies and shops; Metribay reads no customer records. Choose the shop from the list or enter its ID, then enter the shop's timezone. Refunds are not subtracted. See [docs/PRESTASHOP.md](docs/PRESTASHOP.md).
- PrestaShop is handled like Paddle: only people who can manage workspace access can add, change or import it, and they confirm that project viewers and exports include its figures. The configuration import and its checkbox now speak of revenue sources rather than only Paddle.
- The Kestrel Photo sample project gains a PrestaShop print shop.
- New strings in all twelve languages. No migration or configuration change.

## 1.7.3 — 2026-11-27

- **Add data source** no longer dead-ends on a property, site or monitor that a paused data source without a connected account already tracks (for example after a project move). The list marks it as already in the project and paused, with **Choose an account** linking to that data source in the project settings. Entering it manually explains the same.
- Two new strings in all twelve languages. No migration or configuration change.

## 1.7.2 — 2026-11-26

- Moving a project to another workspace pauses each data source whose connected account stays behind, as removing an account does. The project page shows **Paused** with **Choose an account** instead of failing on every run with `ConnectorException`. A GitHub releases data source keeps reading its public repository without a token.
- A new migration pauses data sources that an earlier move left switched on without a required account. Their history stays; choose an account under **Manage data source** and turn on **Collect automatically**.
- A sitemap that lists the same URL more than once, within one file or across files of an index, imports again. The URL counts once and keeps its latest lastmod. WordPress SEO plugins often list the blog page in both their page and post sitemaps.
- No configuration change and no new strings.

## 1.7.1 — 2026-11-25

- Security update for passkeys. The WebAuthn library moves to 5.3.17, which fixes six advisories in attestation and metadata checks, CBOR parsing and how decoy credentials are generated. Laravel Passkeys moves to 0.3, which fixes passkey sign-in on iOS Safari. A passkey that can no longer be used now asks you to remove it and register a new one. Passkeys you already added keep working.
- Laravel 13.44, Fortify 1.44, Livewire 4.4.10, Symfony 8.1 components and chart.js 4.6.2. No database migration or configuration change.
- Hosts that pin `laravel/passkeys` need `^0.3`.

## 1.7.0 — 2026-11-22

- **Add data source** is easier to choose from. Providers are grouped by what they measure: **Website analytics**, **Search engines**, **Uptime and speed**, **Publishing**, **Code and releases**, **Social followers** and **Revenue**. Each group says what it is for and what it needs, so a search engine group is clear even if you have never used Search Console.
- Every provider shows one line on what you get from it, how many data sources of it the project already has (for example "1 added") and **No sign-in needed** when it works without a connected account.
- **Find a provider** filters the list by name, what it measures or group, and ignores accents.
- Each provider links to its **Setup guide** on metribay.com, in your language. After you choose a provider, a link to the same guide stays above every step.
- Hosts can change the docs address with `metribay.marketing_url`. New strings in all twelve languages. No database migration or configuration change.

## 1.6.0 — 2026-11-19

- **Audience breakdowns** for Google Analytics 4, Plausible, Umami, Matomo and PostHog (website and product usage). The **Audience** group now ends with two blocks:
  - **Acquisition**: visits by **Channel**, with a daily stacked chart and each channel's share and change, plus top **Referrers**, **Landing pages** and **Campaigns** (`utm_campaign`). Every provider's channels map onto one list: direct, organic and paid search, organic and paid social, email, referral, AI assistants, video, shopping, display, affiliates, other.
  - **Locations and devices**: top **Countries** and **Devices** (desktop, mobile, tablet).
- **Bounce rate** and **Average visit duration** charts in **Audience**. A period's rate is calculated from all its bounces and visits, never by averaging daily rates.
- **Conversions**: completed goals and key events per day from Google Analytics, Plausible and Matomo, in a new **Conversions** group with a **Goals** list (Umami: custom events). A site without goals shows no Conversions group instead of a row of zeros.
- Channels are stored per day and follow any period, including custom ranges. The other lists are stored for the last 7, 30 and 90 days and the period before each; Yesterday and custom ranges show the closest one and say so. Umami reports channels per period only. Lists count visits where the provider has them and visitors otherwise, and visitors in different rows are never added up.
- Breakdowns never stop a sync: if a provider can't answer one (an older self-hosted version, a disabled Matomo plugin), the other numbers still sync and that list keeps its last values. The lists refresh about once a day per data source, spread over the day's syncs, to stay within provider rate limits; channels and conversions update with every sync. No new permissions are needed.
- Each block can be hidden per data source like a chart.
- The demo shows breakdowns for every sample analytics source; Alder & Finch's bot spike now shows up as Direct.
- New migration: `channel_days` and `breakdown_rows` tables and `sources.breakdowns_collected_at`. New strings in all twelve languages. No configuration change.

## 1.5.0 — 2026-11-18

- Sign in with a **passkey**: Touch ID, Face ID, Windows Hello, a phone or a security key. Add one under **Profile → Passkeys** after confirming your password, then choose **Sign in with a passkey** on the sign-in page. A passkey replaces both the password and the two-factor code, and requires the device's fingerprint, face or PIN. Your password and two-factor authentication keep working.
- **Confirm password** pages offer **Use a passkey** when you have one. Profile lists each passkey with when it was added and last used, and removes it after a fresh confirmation.
- Passkeys are opt-in, like two-factor authentication. Hosts enable them with Fortify's `Features::passkeys(['confirmPassword' => true])`; the standalone host and Docker image do. Passkeys need `APP_URL` to be the exact address people open, with a host name rather than an IP address, served over HTTPS (or `localhost` while testing).
- A failed passkey check (wrong site, replayed or invalid signature) is refused with a message instead of a server error. Passkey sign-in and confirmation are rate limited per session and per IP address.
- New migration: `passkeys` (skipped when a host already has that table). New strings in all twelve languages. Standalone test runs set a 512M memory limit. Core now requires `laravel/fortify` ^1.41 and `laravel/passkeys` ^0.2.1.

## 1.4.2 — 2026-11-18

- Check access before adding a GitHub repository. When access is lost, project settings explain the issue and let you retry collection on the same data source.

## 1.4.1 — 2026-11-18

- Adding a data source no longer fails with an error page (“409 Conflict”) when another Metribay page was opened during setup, for example in another tab while you create an API key. The step you submit now continues: choosing a provider, or saving a connected account with the server address and key you typed. A later step that can't continue starts the setup again for the same project with a short explanation. Browser prefetches and background requests no longer end a setup at all.
- New string in all twelve languages. No database migration or configuration change.

## 1.4.0 — 2026-11-18

- Connect **PostHog**. Add a data source, choose **PostHog**, connect PostHog Cloud (US or EU) or a public self-hosted server with a personal API key that can read Project, Query and Event definition, and pick a project. A project API key (`phc_…`) only sends events and is rejected. Then choose **What to track**:
  - **Website and product usage** records visitors, visits and page views in **Audience**, and **Active users** (people with any event) in a new **Product** group. Visitors and active users over 7, 30 and 90 days are counted once per period, never added up day by day.
  - **One key event** records the daily count of one event you name, such as `user_signed_up`, as **Key events** in **Product**, labelled with the event name. Add the project again for each event you want to follow. A misspelt event name fails the sync instead of showing zeros.
- Dates follow the PostHog project's timezone. Days after the project was created with no events are zero; earlier days stay empty. PostHog history is imported for the chosen window.
- Projects with PostHog get a **Product** view next to **Search**, **Development and Issues** and **Revenue**. The overview gains no column: active users can't be added across sources, and different events shouldn't be.
- The demo's Tallybird project shows its app in PostHog: usage, plus `user_signed_up` and `invoice_sent` key events.
- New strings in all twelve languages. No database migration or configuration change.

## 1.3.1 — 2026-11-15

- The demo's sample blog feed no longer shows **Delayed**. Its posts are now counted back from yesterday, so the newest post is always recent; a test checks that no sample data source looks delayed on any day.
- Dependency updates for the standalone host and Docker image: Laravel 13.42.0 (fixes advisory GHSA-63hm-g96r-2m2q), Vite 8.3.8 and patch/minor development tools. No database migration or configuration change.

## 1.3.0 — 2026-11-15

- Track **Followers** of public Bluesky and Mastodon accounts. Add a data source, choose **Bluesky** or **Mastodon** and paste the handle, `name@server` or the profile link. No login, API key or connected account is needed: Metribay reads the public count from Bluesky's public API and from the account's own Mastodon server. Neither network publishes a follower history, so Metribay keeps one count per day (UTC), updated at each sync, from the day you add the source. Projects with these sources get a **Social** chart group and the overview a **Followers** column, which adds a project's accounts together.
- The demo's Driftwood project shows Bluesky and Mastodon followers.
- New strings in all twelve languages. No database migration or configuration change.

## 1.2.0 — 2026-11-14

- Remove a connected account even while data sources use it. **Remove connected account** in **Settings → Connected accounts** now shows how many data sources will pause and asks you to confirm. Removing the account deletes its saved credentials. Data sources that need an account pause, keep their history and show **Paused** with a link to choose another account. Choosing one under **Manage data source** and turning on **Collect automatically** resumes collection. A GitHub releases source keeps reading its public repository without the token. Previously an account in use could only be removed after switching all its data sources to another account.
- Google accounts connected by signing in with Google offer **Also revoke Metribay’s access at Google** when you remove them. It is off by default. Google withdraws the whole grant for that Google login, so other connected accounts created with the same Google login stop collecting too, also in other workspaces. Revocation runs after the account is removed. If Google doesn't confirm it, the account is still removed and Metribay says where to remove access in the Google Account. Service-account keys have no revocation option.
- The read-only demo no longer marks its sample data sources as **Needs attention** a few hours after it starts. With collection switched off (`METRIBAY_COLLECTION_ENABLED=false`), the time since the last sync is no longer treated as a failure; recorded collection failures and uptime incidents still show. Installations that collect are unchanged. No database migration.
- The README data source table now lists RSS / Atom, Sitemap and Paddle Billing.
- New strings in all twelve languages. No database migration or configuration change.

## 1.1.2 — 2026-11-11

- Sitemap sources now track **New URLs detected** internally after an initial baseline. Missing or changing `lastmod` values no longer hide or inflate new-page counts. Tracking survives optional event settings, removed/returning URLs and project moves. Feed **Items published** stays separate. Both sitemap metrics appear in the overview and each project’s **Content** section. Existing sources establish their baseline on their first successful collection after upgrading; earlier detections stay unknown. Adds a URL-history table and a nullable source baseline timestamp. Run migrations and keep the additive schema when rolling back.

## 1.1.1 — 2026-11-10

- Choose **Search**, **Development and Issues** or **Revenue** on a project, or save a named mix of the chart groups that project has. **Overview** and **All metrics** stay as they are, and All metrics remains the default. A saved name stays in this browser for that project. The address keeps the view, the reporting period and the query archive, so a link still opens the same charts. No database migration.

## 1.1.0 — 2026-11-09

- Open a project's **Overview** for recent uptime incidents and significant drops in Google Search Console clicks. **All metrics** opens by default. Fold chart groups to focus on the ones you need. Your browser remembers which groups you folded for each project.
- The project reporting controls and wide tables fit narrow screens.

## 1.0.0 — 2026-11-09

- Metribay 1.0.0, the first stable release. The code is the same as 0.17.1, after the 0.17 Google reconnect and account security work. No database migration or configuration change: update an existing 0.17.1 install by pulling the new image, or with `composer require metribay/core:^1.0` (a `^0.17` constraint does not cross to 1.0).
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

## 1.9.0 — 2026-11-28

- Sitemap sources now track **New URLs detected** internally after an initial baseline. Missing or changing `lastmod` values no longer hide or inflate new-page counts. Tracking survives optional event settings, removed/returning URLs and project moves. Feed **Items published** stays separate. Both sitemap metrics appear in the overview and each project’s **Content** section. Existing sources establish their baseline on their first successful collection after upgrading; earlier detections stay unknown. Adds a URL-history table and a nullable source baseline timestamp. Run migrations and keep the additive schema when rolling back.

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
