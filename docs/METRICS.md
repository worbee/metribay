# Metric model

Daily rows are observations, not a gap-filled time series. A missing row is unknown, explicit0 is known. Counts/lines sum only reported rows; coverage and provisional flags describe incomplete intervals. Visitors use independent exact period queries and never sum across days or unrelated sources. Standing indexed-page counts and present-state GitHub/UptimeRobot metrics use latest observations.

Default cards cover7/30/90 provider-local calendar days including today. Today may be provisional. Previous comparisons use the adjacent equal-length window. Every metric carries actual bounds, timezone and coverage. Percent change against missing/zero baseline is unavailable. Uptime/status/response-time observations do not get generic growth percentages.

All writes validate known metric keys, provider ownership, finite nonnegative numbers, valid dates and percent/status ranges before one atomic transaction. Canonical date-only setters prevent SQLite timestamp text from breaking metric identities. Daily first collected_at is preserved; changed numeric values advance revised_at, and observed_at records latest contact. Snapshot replacement removes omitted same-day readings while retaining present data. Uptime rows carry exact trailing24h window bounds.

Source identities are immutable combinations of provider/resource/timezone. Rename, pause, window changes and project archival retain history. A replaced resource requires a new source. Collectors fetch outside SQL write transactions, then recheck source/credential versions and enable/archive state before persisting. Failures preserve prior observations and expose only safe error categories.
