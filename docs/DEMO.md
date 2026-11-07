# Demo mode

A public, read-only Metribay with the fictional sample portfolio from [DEMO_DATA.md](DEMO_DATA.md). It runs at https://demo.metribay.com and starts with one command from the Docker image.

```sh
docker run --rm -p 127.0.0.1:8080:8080 -e APP_URL=http://127.0.0.1:8080 -e APP_KEY="$(docker run --rm ghcr.io/worbee/metribay key)" ghcr.io/worbee/metribay demo
```

## What visitors get

- **Automatic sign-in.** The first request signs the visitor in as the sample owner (`metribay.demo.email`, default `demo@metribay.example`). There is no login page to get past.
- **Everything visible, nothing changeable.** Every button and form is there. Any request that would change something is refused before it reaches a controller, with the notice "Not available in the demo." (HTTP 403 with a JSON message for API-style requests). Sign-in with Google and other provider redirects are refused too.
- **Still allowed:** reading every page, Livewire re-renders (sorting, periods, date ranges), the language (kept in the visitor's session; `?lang=` works while signed in) and light or dark mode (kept in the browser).
- **A banner** under the header: the data is fictional, with **Start a 14-day trial** (`metribay.demo.trial_url`) and **Self-host it** (`metribay.demo.source_url`).
- **No indexing.** Every response carries `X-Robots-Tag: noindex, nofollow`.

## Why nothing can be written

Two layers, so a request the middleware misses still can't change anything:

1. `Http\Middleware\DemoMode` (enabled by `METRIBAY_DEMO=true`) refuses unsafe requests. It sits right after the session starts in the middleware priority, before authentication and route model binding. Allowed exceptions: `metribay.locale` and Livewire updates.
2. The database is opened read-only: `DB_READ_ONLY=true` sets `Pdo\Sqlite::OPEN_READONLY` on the SQLite connection in `standalone/config/database.php`, and the Docker `demo` role also makes the file mode `0444`. Sessions and cache use files, the queue runs inline, collection and mail are off.

`tests/Feature/DemoModeTest.php` sends every registered unsafe route and checks that each is refused and no table changes.

## Docker roles

| Role | What it does |
|---|---|
| `demo` | Builds the portfolio into `/var/lib/metribay/demo.sqlite`, then serves it read-only in demo mode |
| `demo-refresh` | Rebuilds into `next-demo.sqlite` and swaps it in with a rename. Requests keep working throughout; open sessions are signed in again |

The portfolio's dates are relative to the day it's built, so rebuild it daily: `docker exec <container> metribay demo-refresh`.

Usage analytics stay off unless `METRIBAY_ANALYTICS_ENABLED=true`. Then visitors see the consent banner and accepted visits count as their own edition, `demo` (hostname `metribay.demo`, paths `/demo/{page}`, consent cookie `metribay_demo_usage`).

## Settings

| Variable | Default |
|---|---|
| `METRIBAY_DEMO` | `false`; the `demo` role sets `true` |
| `METRIBAY_DEMO_EMAIL` | `demo@metribay.example` |
| `METRIBAY_DEMO_TRIAL_URL` | `https://app.metribay.com/register` |
| `METRIBAY_DEMO_SOURCE_URL` | `https://github.com/worbee/metribay` |
| `DB_READ_ONLY` | `false`; the `demo` role sets `true` |

Never point demo mode at a real database: it signs every visitor in as that account.
