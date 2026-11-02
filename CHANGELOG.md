# Changelog

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
