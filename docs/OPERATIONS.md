# Operations

Use HTTPS, APP_ENV=production, APP_DEBUG=false, secure session cookies and a separately protected APP_KEY. Keep environment files, database files and backups outside publicly served paths. Bypass CDN caching for the entire authenticated hostname; origin sends no-store, but an edge override can still cache private HTML. Never cache account, workspace or chart routes.

Run migrations before restarting the web and queue processes. Restart long-lived workers after deployment. Publish package assets after installing an updated core release. `/up` is application liveness; source health and failed queue jobs establish collection health.

Use `metribay:sync` to enqueue sources, `--workspace=slug`, `--kind=provider`, `--source=id` to narrow, `--except=provider` to exclude one kind, or `--inline` for a bounded operator check. `--full` rereads the configured history window. First collection reads the configured history window (default 180 days). Later analytic collections recheck the last successful contact minus 7 days, bounded by that window. GitHub always recomputes its full history; Bing returns its provider retention. Exact visitor period queries remain independent. A weekly full sync reconciles older changes. Jobs contain IDs, never credentials. Workers allow up to 4 actual exceptions within 6 hours; waiting for a connection lock does not exhaust exception retries. Provider collection uses per-source uniqueness and connection non-overlap; uptime additionally coordinates request limits by credential hash through shared cache.

SQLite suits a personal installation. Use one process-manager-controlled queue worker initially. Hosted PostgreSQL uses shared workspace-scoped records. A shared cache/queue is required before adding workers/hosts. Keep DB_QUEUE_RETRY_AFTER=960 and worker timeout900 or larger valid equivalents. Avoid multiple schedulers without a shared lock store.

Back up database and APP_KEY separately and verify a restore. SQLite: use SQLite online backup rather than copying live WAL files. PostgreSQL: pg_dump/pg_restore with operator-protected credentials. Credential ciphertext cannot be recovered without APP_KEY. Do not rotate APP_KEY without a tested decrypt/re-encrypt plan.

Production email/Stripe/domain setup uses operator credentials. Core manual connections work without a proprietary identity service. Self-hosted registration is disabled; initialize first owner with metribay:install, and use the email password-reset flow after configuring mail transport.

## Account security

Changing your sign-in email in **Profile** requires your current password. Name and language changes do not. A changed email must be verified again. After a password change or reset, older sessions must sign in again before using account settings, MFA endpoints or the dashboard; the session performing a password update stays signed in.
