# Contributing

Thanks for looking. Bug reports, fixes, translations and new data sources are all welcome.

## Before you start

For a bug, open an issue with the steps to reproduce it. For anything bigger than a small fix, open an issue first and describe what you want to change. A new data source or a change to how a metric is counted is easier to agree on before the code exists.

Security problems don't go in issues. See [SECURITY.md](SECURITY.md).

## Set up

You need PHP 8.5, Composer 2 and Node 24. PostgreSQL is optional locally but CI tests it.

```sh
./scripts/setup.sh --build-assets
cd standalone
php artisan metribay:install --email=you@example.com
```

`scripts/demo-database.sh` builds a realistic sample portfolio into `standalone/database/demo.sqlite`, which is handier than an empty install. See [docs/DEMO_DATA.md](docs/DEMO_DATA.md).

## Checks

Run these before you open a pull request. CI runs the same.

```sh
cd standalone
composer test                 # SQLite
DB_CONNECTION=pgsql DB_DATABASE=metribay_test DB_USERNAME=… DB_PASSWORD=… php artisan test
composer analyse
vendor/bin/pint ../src ../tests ../database ../routes ../config app tests
cd ..
npm test
npm run build                 # commit the changed dist/ files with any UI change
```

## Rules the code depends on

**Unknown isn't zero.** A missing day stays missing. Don't fill gaps with 0.

**Unique visitors don't add up.** Daily visitors are never summed into a period total. Ask the provider for the period instead.

**Provider timezones stay.** Store dates the way the provider reports them and keep provisional days marked.

**Collection is atomic and idempotent.** Running a sync twice gives the same rows.

**Credentials stay encrypted and scoped.** Every query and job runs inside a workspace context. Jobs carry IDs, never credentials.

**No real data in tests.** Fixtures are synthetic: `.example` domains, made-up numbers, no real tokens. That includes screenshots.

## Translations

The app ships in twelve languages. English text is the translation key. When you add or change a string, add it to all eleven files in `lang/`, sorted by key. If you can't translate a language, say so in the pull request and leave the English text there; someone will follow up. Details: [docs/LOCALIZATION.md](docs/LOCALIZATION.md).

## Pull requests

Keep them small and focused on one change. Describe what changed and how you tested it. Add a line to `CHANGELOG.md` under Unreleased for anything a user would notice. New behaviour needs a test.

## License

By contributing, you agree that your contribution is licensed under Apache-2.0, like the rest of the project (section 5 of [LICENSE](LICENSE)).

Everyone taking part follows the [code of conduct](CODE_OF_CONDUCT.md).
