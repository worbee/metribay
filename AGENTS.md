# Metribay public core

When inside the Metribay multi-repository workspace, read ../AGENTS.md and ../PROGRESS.md first if those files exist. This repository also installs independently. This repository is public Apache-2.0. Use only synthetic or sanitized fixtures. The root is Composer package metribay/core (Metribay\Core\ namespace); standalone/ is a real Laravel host. Private cloud consumes this package.
Core must never import cloud code. Keep one workspace-aware domain model, with configurable access/entitlement contracts. Preserve unknown != zero, non-additive visitors, provider timezones, partial dates, atomic idempotent collection, and encrypted workspace-specific credentials.
No stages, commits, pushes, coauthor trailers, real secrets, runtime databases, or production infrastructure in source control without explicit instructions. Do not revert other agents.

Every published Core release tag needs a short user-facing note on metribay.com/changelog/. When working in the full workspace, prepare the site note alongside the Core release. In a Core-only checkout, hand off the summary and tag to the site maintainer.
Tests run from standalone/: php artisan test; package tests are included there. PHP 8.5 is the minimum; use installed source, lock files and docs as authority for framework/package versions.
Select checks with docs/TESTING.md: targeted checks during development; full affected suites at handoff, commit and release. Database and fixture/discovery changes require PostgreSQL too; releases keep the full SQLite/PostgreSQL matrix. In the full workspace also follow cloud/docs/VERIFICATION.md.

Localization (twelve languages, files, language resolution, adding strings or languages): docs/LOCALIZATION.md.
