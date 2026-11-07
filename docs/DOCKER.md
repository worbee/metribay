# Docker

One image runs the standalone host in three roles: `web` (FrankenPHP serving HTTP on port 8080), `worker` (queue) and `scheduler`. PHP 8.5 on Alpine, non-root user `www-data` (uid 82). Everything the application writes, including the SQLite database, lives in the volume `/var/lib/metribay`.

The image is published as `ghcr.io/worbee/metribay` for linux/amd64 and linux/arm64, tagged `latest`, `{major}.{minor}` and the exact version on every release. To build it yourself from this repository:

```sh
docker build -t ghcr.io/worbee/metribay:latest .
```

## Try it with sample data

```sh
docker run --rm -p 127.0.0.1:8080:8080 -e APP_URL=http://127.0.0.1:8080 -e APP_KEY="$(docker run --rm ghcr.io/worbee/metribay key)" ghcr.io/worbee/metribay demo
```

Open http://127.0.0.1:8080: the demo signs you in. The `demo` role rebuilds the fictional sample portfolio from [DEMO_DATA.md](DEMO_DATA.md) on every start and serves it read-only, with collection and usage analytics switched off ([DEMO.md](DEMO.md)). The same thing runs at https://demo.metribay.com.

## Run it

```sh
cp docker/.env.example docker/.env
docker run --rm ghcr.io/worbee/metribay key      # paste the output into APP_KEY in docker/.env
docker compose -f docker/compose.yaml up -d
docker compose -f docker/compose.yaml exec web php artisan metribay:install --email=you@example.com
```

The install command asks for a password privately and creates the only account. Registration stays off. Open `APP_URL` and sign in.

Set `APP_URL` to the exact address you use. In production, the standalone host rejects other hostnames, including subdomains, to protect password-reset and verification links. Local health probes must send that hostname in the `Host` header; the image healthcheck does this automatically. For example, with `APP_URL=https://metrics.example.com`, use `curl -H "Host: metrics.example.com" http://127.0.0.1:8080/up`.

`web` runs pending migrations before it starts serving, and `worker` and `scheduler` wait until `web` is healthy. The scheduler starts collection every four hours, uptime hourly and a full history refresh weekly ([OPERATIONS.md](OPERATIONS.md)). The port is bound to `127.0.0.1:8080`; set `METRIBAY_PORT` in `docker/.env` to change it.

### PostgreSQL

SQLite suits one person. For PostgreSQL, set `POSTGRES_PASSWORD` in `docker/.env` and add the second file to every command:

```sh
docker compose -f docker/compose.yaml -f docker/compose.postgres.yaml up -d
```

PostgreSQL 17 keeps its data in the `postgres` volume.

## HTTPS

The container speaks plain HTTP. Put it behind the reverse proxy you already use (Caddy, Traefik, nginx) and terminate HTTPS there. Then set in `docker/.env`:

```sh
APP_URL=https://metrics.example.com
METRIBAY_TRUSTED_PROXIES=172.16.0.0/12   # the proxy's address or network, comma-separated
SESSION_SECURE_COOKIE=true
```

Don't let a CDN cache any page of the app ([OPERATIONS.md](OPERATIONS.md)).

## Settings

All settings are environment variables in `docker/.env`; [`docker/.env.example`](../docker/.env.example) lists the common ones. `APP_KEY` is required: it encrypts your provider credentials, and they can't be recovered without it. Your own Google OAuth app is optional ([PROVIDERS.md](PROVIDERS.md)); service accounts and tokens work without one. Mail is only needed for password reset emails.

Usage analytics are off by default. `METRIBAY_ANALYTICS_ENABLED=true` shows the consent prompt, and statistics are only sent after the user agrees ([USAGE_ANALYTICS.md](USAGE_ANALYTICS.md)).

SQLite runs in WAL mode with a 10 second busy timeout (`DB_JOURNAL_MODE`, `DB_BUSY_TIMEOUT`), so the three containers can share the file. Keep the volume on a local disk, not a network share.

## Backups and updates

Back up the volume and `APP_KEY`, separately. For SQLite, take a consistent copy with the online backup command rather than copying the live file:

```sh
docker compose -f docker/compose.yaml exec web sqlite3 /var/lib/metribay/metribay.sqlite ".backup '/var/lib/metribay/backup.sqlite'"
```

For PostgreSQL, use `pg_dump` in the `postgres` container. Test a restore.

To update, pull or rebuild the image and recreate the containers. `web` migrates on start; set `METRIBAY_MIGRATE=false` to run `php artisan migrate --force` yourself instead.

```sh
docker compose -f docker/compose.yaml up -d --build
```

## Roles and commands

| Command | What it does |
|---|---|
| `web` (default) | Prepares storage, caches configuration, migrates, serves on 8080 |
| `worker` | `queue:work` with a 900 second job timeout |
| `scheduler` | `schedule:work` |
| `demo` | Rebuilds the sample portfolio and serves it read-only, signed in ([DEMO.md](DEMO.md)) |
| `demo-refresh` | Rebuilds the demo database in a running `demo` container and swaps it in |
| `key` | Prints a new `APP_KEY` |
| anything else | Runs as a command in `/app/standalone`, for example `php artisan metribay:sync --inline` |

A bind mount instead of the named volume must be writable by uid 82.

## Checks

CI builds the image and serves the sample portfolio from it on every push (`.github/workflows/ci.yml`, job `docker`). No registry login or secrets are involved.
