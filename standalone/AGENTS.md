# Metribay application

Read ../../PROGRESS.md (standalone) or ../PROGRESS.md (cloud) and the workspace AGENTS.md.
Use standard Laravel authentication, validation, encryption, queues, policies and stable free packages. Never stage, commit or push without explicit authorization. Never add coauthor attribution.
Core owns all dashboard behavior; private code may depend on core, core must never depend on private code. Explicitly scope each request, job, lookup and export to its workspace. Keep credentials out of logs, public fixtures and Git.
Run composer test, composer analyse, and npm run build when relevant. Record checks and remaining work in workspace PROGRESS.md.
