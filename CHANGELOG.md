# Changelog

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
