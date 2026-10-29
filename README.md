# Metribay

A dashboard for the projects you run. Collect website traffic, search visibility, plugin downloads, repository activity and uptime into one place. Metribay reads stored observations; opening the dashboard never calls providers.

This repository contains Composer package `metribay/core` and a complete self-hosted Laravel application in `standalone/`. The personal edition has one administrator with multiple projects and sources. Workspace selection and management belong to Cloud. Core retains one internal data scope per personal owner for queries, jobs and encrypted credentials; it is not exposed as a workspace feature.

## Requirements

PHP8.3+ with cURL, OpenSSL, PDO SQLite/PostgreSQL, intl and mbstring; Composer 2; Node 24 only when rebuilding frontend assets. Laravel13.38.0 was the latest stable release at initial setup on 2026-10-25. Composer lockfiles simulate the PHP8.3 baseline.

## Install

```sh
./scripts/setup.sh
cd standalone
php artisan metribay:install --email=you@example.com
php artisan serve --host=127.0.0.1 --port=8000
```

The install command asks for a password privately. An operator may instead pass `--password-file=/private/path` (14+characters); never pass passwords as command-line arguments. For a deliberately synthetic preview, run `php artisan metribay:demo --email=you@example.com` outside production. Demo sources are disabled and visibly labeled.

Run the queue worker and scheduler in separate processes:

```sh
php artisan queue:work --timeout=900
php artisan schedule:work
```

Production should use a process manager and one cron entry for `php artisan schedule:run`. Database queue retry_after must exceed worker timeout; default960s. General collection runs every 4 hours, uptime hourly, and a full history refresh weekly. See docs/OPERATIONS.md.

## Providers

Umami, Google Analytics4, Google Search Console, Bing Webmaster Tools, WordPress.org, GitHub and UptimeRobot. Connection credentials are encrypted with APP_KEY and bound to their workspace/provider. HTTP connectors only reach public HTTPS endpoints with pinned DNS, bounded responses and no redirects. Self-hosted analytics on a private network are intentionally unsupported by this transport.

Missing observations stay unknown. Daily unique visitors are never summed into a period total. Reporting timezones, provisional dates, coverage, rolling uptime windows and source health remain visible. See docs/PROVIDERS.md and docs/METRICS.md.

## Development

```sh
cd standalone
composer test
composer analyse
vendor/bin/pint ../src ../tests ../database ../routes ../config app tests
cd ..
npm ci
npm run build
php standalone/artisan vendor:publish --tag=metribay-assets --force
```

The default installer uses bundled assets and needs no Node installation. Use `scripts/setup.sh --build-assets` to rebuild them.

`dist/` is included in release source so installed Composer packages have usable assets. Rebuild it alongside UI changes. Branding (the soft tray lockups, favicon and touch icon in `resources/images/`, copied into `dist/` by the build) and bundled third-party licenses are preserved. Tests cover provider fixtures, native HTTP transport construction, persistence, workspace isolation, credentials and request flows. Browser checks are recorded separately. Public installs need no private repository or credentials.

## License

Core source: Apache-2.0. Supplied branding belongs to Worbee EOOD. Third-party runtime and bundled asset licenses retain their own terms; see NOTICE and dist/THIRD_PARTY_LICENSES.txt.

Hosts that provide their own local identity can set `metribay.account_enabled` to `false` in host configuration. This hides account navigation and identity controls, returns 404 for the account page, and exposes appearance controls under Settings → Appearance. Hosts remain responsible for disabling any authentication routes they do not use. The default keeps standalone and hosted account behavior enabled.
