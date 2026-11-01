# Metric model

Daily rows are observations, not a gap-filled time series. A missing row is unknown, explicit0 is known. Counts/lines sum only reported rows; coverage and provisional flags describe incomplete intervals. Visitors use independent exact period queries and never sum across days or unrelated sources. Standing indexed-page counts and present-state GitHub/UptimeRobot metrics use latest observations.

Default cards cover7/30/90 provider-local calendar days including today. Today may be provisional. Previous comparisons use the adjacent equal-length window. Every metric carries actual bounds, timezone and coverage. Percent change against missing/zero baseline is unavailable. Uptime/status/response-time observations do not get generic growth percentages.

**Open issues** uses the latest recorded count in the selected window for each source, never a sum across days. The overview adds those source counts for each project and shows totals across projects; unknown sources remain unavailable in the breakdown, while a recorded zero stays zero. The overview sparkline sums a date only when every source has an observation for it; missing dates remain gaps. The project page charts each source's recorded daily snapshots. Neither view shows a percentage change for this snapshot metric, and the current count cannot reconstruct dates before collection began.

Overview columns match the metric keys exposed by each source. A future provider that exposes the registered `open_issues` metric joins the same overview column without a provider-specific dashboard change.

All writes validate known metric keys, provider ownership, finite nonnegative numbers, valid dates and percent/status ranges before one atomic transaction. Canonical date-only setters prevent SQLite timestamp text from breaking metric identities. Daily first collected_at is preserved; changed numeric values advance revised_at, and observed_at records latest contact. Snapshot replacement removes omitted same-day readings while retaining present data. Uptime rows carry exact trailing24h window bounds.

Source identities are immutable combinations of provider/resource/timezone. Rename, pause, window changes and project archival retain history. A replaced resource requires a new source. Collectors fetch outside SQL write transactions, then recheck source/credential versions and enable/archive state before persisting. Failures preserve prior observations and expose only safe error categories.
