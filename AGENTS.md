# Metribay public core

When inside the Metribay multi-repository workspace, read ../AGENTS.md and ../PROGRESS.md first if those files exist. This repository also installs independently. This repository is public Apache-2.0. Use only synthetic or sanitized fixtures. The root is Composer package metribay/core (Metribay\Core\ namespace); standalone/ is a real Laravel host. Private cloud consumes this package.
Core must never import cloud code. Keep one workspace-aware domain model, with configurable access/entitlement contracts. Preserve unknown != zero, non-additive visitors, provider timezones, partial dates, atomic idempotent collection, and encrypted workspace-specific credentials.
No stages, commits, pushes, coauthor trailers, real secrets, runtime databases, or production infrastructure in source control without explicit instructions. Do not revert other agents.

Before opening, updating or reviewing a PR with user-visible changes, check that `CHANGELOG.md` records them under **Unreleased** and that the README and affected docs match. In the full workspace, prepare any affected Site user docs, marketing copy and a draft release note in all twelve locales. Record the companion path and release conditions in the private workspace handoff. In a Core-only checkout, list the affected Site pages and give the maintainer the release copy. In the PR checklist, report coverage or give a reason when an item does not apply. Each published Core tag gets one Site changelog note.
Tests run from standalone/: php artisan test; package tests are included there. PHP 8.5 is the minimum; use installed source, lock files and docs as authority for framework/package versions.
Select checks with docs/TESTING.md: targeted checks during development; full affected suites at handoff, commit and release. Database and fixture/discovery changes require PostgreSQL too; releases keep the full SQLite/PostgreSQL matrix. In the full workspace also follow cloud/docs/VERIFICATION.md.

Localization (twelve languages, files, language resolution, adding strings or languages): docs/LOCALIZATION.md.
