# Changelog

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
