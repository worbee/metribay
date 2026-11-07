# Choosing Core checks

Preserve distinct failure cases. Optimize fixtures and repeated work before deleting coverage. Copy or styling alone does not need a new test. Keep HTTP/authorization/isolation, malformed-provider, metric and transaction contracts covered. Test fixtures are synthetic; never run RefreshDatabase against an application database.

## Development

From standalone/, run the affected file or method:

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test ../tests/Feature/DemoModeTest.php
DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test --filter=ProjectLayoutTest
```

Use npm test from core/ for JavaScript changes. Pure calculation tests need no database records; keep framework boot only when translations/container behavior needs it. UI checks use minimal projects; full sample-portfolio generation is tested separately. Reuse a passing check until another edit/failure justifies rerunning it.

## Handoff and commit

For executable PHP/test changes, run the complete Core suite on SQLite, composer analyse and Pint on changed PHP. Database/schema/query, tenant scope, transactions/locking, queue or test fixture/discovery changes also require the complete PostgreSQL suite. Use an isolated loopback database with a name ending in _test and explicit connection settings; clear DB_URL so a saved URL cannot override them. For database-independent helpers/mock-only connectors, targeted SQLite checks during development are enough; complete affected-suite checks still apply at handoff.

For JavaScript changes run npm test. Asset changes require npm run build; committed dist/ must match the source. Layout, chart or interaction changes need the affected browser flow, representative narrow/wide viewports and relevant themes/locales. scripts/check-overview-layout.mjs checks the synthetic overview, not every app page; its local demo must already be running. No browser download or new dependency is implicit.

## Release

Run the complete Core suite on SQLite and PostgreSQL, composer analyse, Pint, JS tests and the asset build. Require the Core CI/Docker demo smoke checks for a release; Docker/runtime/bootstrap/dependency changes need the image smoke check before handoff as well. Shared-contract changes require consumer-host integration checks. Do not repeat the entire release matrix after prose-only follow-ups to the exact code already checked.

The normal command from standalone/ is php artisan test. Configure PostgreSQL through explicit DB_CONNECTION=pgsql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD; never place real credentials in command examples or public fixtures. The CI workflow is the executable database matrix; static analysis and formatting run once, not once per engine.

In a full workspace, cloud/docs/VERIFICATION.md defines cross-repository selection and the optional scoped check-workspace.sh runner. Its default local profile is not the complete release matrix. Record commands, database/fixture context, results and skipped checks in the current handoff. Do not stage, commit, push or release without the corresponding explicit request.
