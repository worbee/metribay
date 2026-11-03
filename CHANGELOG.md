# Changelog

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
