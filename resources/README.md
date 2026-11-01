# Shared product interface

The public standalone and private cloud hosts render these same Blade views and consume the same built assets. The package contains no host-specific Vite dependency and loads no CSS, JavaScript, font, or chart library from a CDN.

## Build and publish

From `core/`:

```sh
npm ci
npm run build
php standalone/artisan vendor:publish --tag=metribay-assets --force
php ../cloud/artisan vendor:publish --tag=metribay-assets --force
```

Build output is `dist/app.js`, `dist/app.css`, the supplied original light/dark logo SVGs, and the bundled third-party licenses. Each host publishes these to `public/vendor/metribay`. Asset URLs include the published file modification time so releases invalidate browser caches.

Vite and Chart.js versions are locked in `package-lock.json`. Chart.js provides line rendering, partial-data points, interactive tooltips, keyboard arrow-key navigation, and keyboard-operable legend controls. Missing values remain null and `spanGaps` is false. Dates are reporting-date labels, formatted in UTC to prevent browser timezone shifts; source reporting timezones are shown alongside the charts. Prior periods are aligned by relative day and their original dates appear in tooltips. Detailed charts include an exact-value HTML table.

## Product surface

- `layouts/app` and `layouts/auth` share branding, themes, errors, flash messages, scripts, and styles.
- Fortify supplies login, reset, registration (cloud), email verification, password confirmation, and two-factor challenge endpoints.
- Account MFA secrets and recovery codes are only rendered after recent password confirmation.
- `components/overview` is the view for the PHP Livewire dashboard component. Date and group filters update server state; project-name filtering runs locally.
- The overview offers Add project; each project page offers Add data source and Project settings. Project detail contains data source health, daily charts with event markers, comparison controls and event create/edit/delete forms.
- Events (`ProjectEvent`, table `project_events`) are custom (one-off or weekly/monthly/yearly with an optional end date and one of four colors) or automatic (provider-imported, such as GitHub releases, drawn in a neutral color with a tag icon and read-only). Recurrences expand per request within the visible date range.
- Terminology: a *connected account* (`Connection`) is a reusable workspace login at a provider; a *data source* (`Source`) is one tracked resource attached to one project through a connected account. Settings show Projects, Connected accounts and Import & export; data sources are managed in each project's settings.
- Interface text uses Laravel translations: Core ships JSON packs plus validation, auth, password and pagination files under `lang/` for each offered locale (English is the source/fallback; other locales include `de`, `bg`, `sr`, `pl`, `es`, `it`, `fr`, `nl`, `cs`, `sl` and `sk`). `metribay.locales` lists the offered languages; the user's choice is stored in `users.locale`, guests use the session or browser language, and notifications use `HasLocalePreference`. Hosts add their own JSON translations, which override Core's.
- Settings use the server's provider metadata, with credentials separated from resource configuration. Credential fields are never repopulated. Connection editing retains all saved credentials only when every field is blank; otherwise the server validates one complete replacement authentication method.
- The workspace-creation link is a host extension configured through `metribay.workspace_creation_route`; the public package does not depend on private route names. It also requires a positive entitlement from `canCreateWorkspace`.
- Every mutation includes CSRF, and updates include the backend version where required. Template visibility follows `canEdit`, `canManageConnections`, and navigation extensions; authorization remains server enforced.

## Visual system and preferences

Warm neutral light surfaces or a navy dark surface, one magenta action/chart accent, thin structural dividers, and sparse lime source-health dots. Project lists and charts are the primary working surface. System font stack; no external font requests. Theme, number style, and overview density are saved only in browser localStorage. Metric tables retain exact values. Reduced-motion settings remove transitions; layouts support narrow screens and print.

## Validation

Run `php artisan view:cache` from each host and lint its compiled Blade PHP after template changes. Use the real locally seeded host for browser checks. Check 1440px and 390px layouts, both themes, Livewire date/group updates, settings provider switches, chart legends/keyboard controls, events, profile settings, language switching across offered locales and authentication. Do not claim provider access verified from synthetic charts. Source credentials and real provider requests are separate integration checks.
