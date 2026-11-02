# Metribay public core

When inside the Metribay multi-repository workspace, read ../AGENTS.md and ../PROGRESS.md first if those files exist. This repository also installs independently. This repository is public-intended. Use only synthetic or sanitized fixtures. The root is Composer package metribay/core (Metribay\Core\ namespace); standalone/ is a real Laravel host. Private cloud consumes this package.
Core must never import cloud code. Keep one workspace-aware domain model, with configurable access/entitlement contracts. Preserve unknown != zero, non-additive visitors, provider timezones, partial dates, atomic idempotent collection, and encrypted workspace-specific credentials.
No stages, commits, pushes, coauthor trailers, real secrets, runtime databases, or production infrastructure in source control without explicit instructions. Do not revert other agents.
Tests run from standalone/: php artisan test; package tests are included there. Framework version is locked to latest stable Laravel 13.38.0 at setup (2026-10-25); use installed source/docs as authority.

Localization (twelve languages, files, language resolution, adding strings or languages): docs/LOCALIZATION.md.
