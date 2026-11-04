# Project page design

Status: mockup approved by Rob (2026-11-02); released in Core 0.8.0 (production 20261103-01, 2026-11-03). Top queries and the move of the event list to its own page ([EVENTS_PAGE.md](EVENTS_PAGE.md)) followed in Core 0.9.0. Mockup: [mockups/project-page.html](mockups/project-page.html) (static, synthetic data). Configurability is recorded below but deferred.

## Why

Today every metric is the same ~410 px card: a 35 px number, a 215 px line chart and six lines of small print. A project with five sources and 13 metrics is about 4,500 px tall at 1440 px (five screens) and 6,800 px on a phone; the first desktop screen shows two numbers. Sections follow providers, so related numbers (visits and search clicks, uptime and response time) sit far apart, and status values such as "Up" are drawn as line charts.

## Principles

1. **The index summarises, the project page shows everything.** Someone who opens a project wants more, not less. No metric moves behind an extra click, drawer or detail page. Every chart stays visible and interactive.
2. **Hierarchy comes from grouping, size and chart type**, not from hiding.
3. **Same language as the index.** Group order follows the index columns; changes use the index's arrows, rounding and colours.
4. **Say it once.** Small print that is identical on every chart (legend, unit, full coverage) is shown once per group or only when it carries information.
5. **Core data rules are unchanged:** unknown stays "—" and never 0, missing days stay gaps, partial days stay marked, visitors are never summed, percent change against a missing or zero baseline is unavailable, the daily-values table stays available for every chart.

Target: at 1440 × 900 every headline number and the first group fit on the first screen; a project with 13 metrics fits in about two screens.

## Page structure

1. **Compact header** (one block, about 110 px):
   - "All projects" link, favicon, project name (h1, about 26 px), domain link.
   - Status line: number of data sources, last sync, and a single health state ("All data sources healthy" / "1 needs attention" / "1 paused") with attention and delayed counts linking to the affected block on the page. A down monitor targets its visible status strip; collection problems and hidden monitor charts target the source warning. When several sources share a state, named links beside the count reach each one. Target blocks are outlined, and warnings remain visible even if all charts in the group are hidden. The separate **Review in settings** link remains available to editors. Other source details, including timezones and sync times, stay in provider tooltips and Project settings.
   - Right side: period as a segmented control (**Yesterday** / 7 / 30 / 90 days / **Dates…**; see Reporting period), secondary buttons **Events** (with the number of event markers in the period; opens the events page), **Add event** and **Add data source**, icon button for Project settings. No primary (pink) button on this page.
2. **Summary row (optional, evaluate in the mockup):** the project's own index row (same columns, numbers, changes and sparklines). Each cell links to its group. It is a recap of the row the user clicked, not a new layer.
3. **Groups**, in index column order. Each group has a heading, the provider labels it draws from, and one legend (selected period, previous period, events). The legend's event count links to the events page.
4. **Footnote** (existing data note), unchanged.

Events are not listed on this page: their markers stay on the charts, and clicking one opens it on the project's events page (docs/EVENTS_PAGE.md).

## Reporting period

The overview (a menu) and the project page (the segmented control) share one period, `Metrics\Period`, carried in the URL:

- **Last 7, 30 or 90 days** (`?days=7|30|90`) end today, which may be partial.
- **Yesterday** (`?days=1`) is the last complete day, compared with the day before. The project page shows every metric as a number tile (no one-point line charts; sparklines need two points). Unique visitors use the day's daily value, which is that day's unique count.
- **Custom range** (`?from=YYYY-MM-DD&to=YYYY-MM-DD`): calendar dates, both included, up to 731 days and ending no later than today (tomorrow in UTC is accepted for timezones ahead). The overview's **Custom range…** opens From/To fields with **Apply** and **Change dates**; the project page's **Dates…** opens the same fields as a small form. Invalid ranges get a message on the overview and HTTP 422 in the URL.
- Every period compares with the same number of days just before it. Dates are read in each data source's reporting timezone; a custom range is the same calendar dates everywhere. Health (failed, delayed, monitor down) always reflects today, whatever period is shown.
- Unique visitors exist only as provider totals for the last 7, 30 and 90 days (and single days); custom ranges show "period total unavailable" with the daily chart. Top queries use the stored window closest in length (7, 30 or 90 days) and say so; a custom range that is exactly an archived month opens that month.
- Links between the overview, project page and breadcrumb keep the period; the events page receives the nearest of 7/30/90 days.

## Groups and default sizes

| Group | Metrics (lead first) | Providers |
| --- | --- | --- |
| Audience | **Visits**, Visitors, Page views | Umami, Google Analytics |
| Search | **Clicks**, Impressions, Indexed pages | Search Console, Bing Webmaster |
| Development | **Commits**, PRs merged, Lines changed, Open issues, Pipeline runs succeeded / failed | GitHub, Azure DevOps |
| Distribution | **Downloads**, Release downloads | WordPress.org, GitHub releases |
| Reliability | **Monitor status** (status strip), Uptime, Response time | UptimeRobot |
| Page speed | **Lab performance score**, lab LCP / CLS / TBT, real-user page and site LCP / INP / CLS | PageSpeed Insights |

Sizes (desktop grid of six columns):

- **Large** (lead): four columns, number about 30 px, previous-period value in words, chart about 160 px with y-axis labels and event markers. One per group.
- **Small**: two columns, number about 20 px, chart about 64 px with two gridlines and start/end dates only. Smalls fill the column beside the lead first (stacked, sharing its height), then continue in rows of three.
- **Number**: value, change, threshold or status, and a sparkline; no axis. Used for snapshot and quality values (open issues, Page speed values).
- **Status strip**: one cell per day (up, down, partial, unknown), with the current state and "29 of 30 days up". Replaces the line chart for monitor status.

Layout rules:

- A group with a single chart, or that fits in three columns, takes half the width and pairs with the next group of the same kind (for example Distribution beside Reliability).
- If a group's lead metric is missing, the next metric in the table becomes the lead.
- When two sources provide the same metric (two repositories, Umami and GA4), each gets its own chart labelled with the source; the first source in project order provides the lead.
- Charts without any data in the period collapse to one line ("Response time · no data for this period") instead of a full card.

## Chart chrome

- **Legend:** once per group, not per chart.
- **Unit line** ("Count", "Milliseconds"): removed; units live in the number formatting (ms, %, score /100).
- **Coverage:** shown only when incomplete ("27 / 30 days recorded"). Snapshots keep "Observed 1 Nov"; when every value in a block shares the date (Page speed), it is shown once in the block caption.
- **Partial:** today's incomplete value is drawn in the chart itself (dashed last segment, hollow last point, "partial" in the tooltip) instead of a badge on every card. The Partial badge remains only when earlier dates are incomplete.
- **Hide chart:** icon on hover and keyboard focus; always visible on touch screens.
- **Daily values:** stays per chart as a quiet "Daily values" link that expands the existing table in place.
- **Provider label:** on each chart only when the group has more than one provider.
- **Aligned dates:** all charts in a period share the same x-axis; hovering one chart shows a crosshair at the same date in every chart and that day's value in the hovered chart.

## Top queries

Search Console and Bing data sources also report their top search queries. The **Search** group ends with a full-width **Top queries** block (the group is never paired at half width when it has one):

- Lists: **Clicked** (queries with clicks, most clicks first; the default unless nothing was clicked), **Ranked** (every query, most impressions first, including queries without clicks) and **Opportunities** (average position 4–20, at least 10 impressions and a click-through rate below the site's average, most impressions first). The tab counts follow the selected data source.
- Columns: query, clicks with the change against the window before (index arrows and colours), impressions, CTR, average position with its change in positions (a lower position is better: ↗ means it moved up; coloured from one whole position). Queries missing from the previous window are labelled **New** when that window has data.
- 10 rows per list; **Show all :count** expands the table in place. Long queries are truncated with the full text on hover; on phones the table scrolls inside its frame.
- One source switch (**Google Search Console** first, then **Bing Webmaster**) when both are connected; their positions and windows are never merged.
- Caption: provider, window and **Partial** while Google's latest dates are not final. Query windows end on the provider's latest reported date, so they can end before the page's period: Search Console uses the same number of days (for example 3 Oct – 1 Nov), Bing reports weekly and uses the latest 1, 4 or 13 weeks ("Weekly data: 4 weeks to 30 Oct"). When the listed queries hold less than 95 % of the site's clicks for the same days, the caption says so ("These queries account for 71 % of the site's clicks; Google leaves out rare queries.").
- The block can be hidden per data source like a chart (hidden-chart key `top_queries`) and is restored from **Hidden charts**. The chosen source, list and expanded state survive the hourly refresh.
- **Monthly archive.** When months are archived, a menu beside the heading offers the current window (**Last 30 days**) and each archived month, newest first (`?queries=2026-10`). A month is compared with the month before (**vs September 2026**); a second menu picks another archived month or **No comparison** (`?compare=2026-06|none`). The caption names the month and the comparison ("October 2026 · Compared with September 2026"; Bing: "Weekly data: 4 weeks dated in October 2026"). **New** means the query is not among the compared month's stored queries. A source without that month says "No queries were archived for October 2026." Unknown months fall back to the current window.

Storage: `search_queries` holds, per data source and window (7, 30, 90 days and the window before each), the top 500 queries by clicks (Search Console sorts by clicks; previous windows keep only queries of the current one). Every collection replaces the source's rows as one snapshot (`CollectionResult::$queries`).

The archive is `search_query_months` (one row per source and calendar month: dates covered, Bing's week count, number of queries) with `search_query_month_rows` (query, clicks, impressions, position; top 1,000 per month). A month is written once, after its data is final, and never replaced or pruned (`CollectionResult::$queryMonths`; `SourceConfiguration::$archivedQueryMonths` tells connectors which months exist). Search Console archives complete months whose last day is final, newest first, four per collection, back to its 16-month limit, so a new source fills its history over its first day of syncs. Bing groups its weekly rows by the date of each week and archives a month once a later week exists (the oldest month only if its first week is still kept). Archived months move with their project to another workspace and are deleted with their data source.

Built by `Services\SearchQueries`; view `partials/search-queries`. Tests: `tests/Feature/SearchQueriesTest.php` and the Search Console/Bing cases in `tests/Connectors/ProviderConnectorsTest.php`.

## Change indicator

Matches the index (`partials/overview-change`): ↗ / ↘ / →, one decimal below 10 %, whole numbers from 10 %. Colour applies from 10 % and depends on the metric's polarity:

- Higher is better: visits, visitors, page views, clicks, impressions, indexed pages, commits, PRs merged, pipeline runs succeeded, downloads, release downloads, uptime, lab score.
- Lower is better (a rise is red): open issues, pipeline runs failed, response time, every LCP / INP / CLS / TBT value.
- Neutral (never coloured): lines changed.

Large charts also show the previous-period value in words ("468 in the previous 30 days"). No percentage is shown when the previous period is missing or zero (existing rule).

Snapshots (page speed values, open issues, indexed pages, uptime, response time) show a difference in their own unit instead of a percentage: the latest value against the last value of the previous period (`+12` for a score, `−500 ms`, `+0.40 pp` for uptime, `±0` when unchanged; `partials/snapshot-change`). It is coloured by the same polarity from a 5 % relative change. Monitor status has no difference. The overview's uptime column has a sparkline of the lowest daily uptime among a project's monitors, scaled to its own range so small dips show.

## Page speed

Values carry the published thresholds as a quality label (Good / Needs improvement / Poor) and colour: score ≥ 90 / 50–89 / < 50; LCP ≤ 2.5 s / ≤ 4 s / > 4 s; INP ≤ 200 ms / ≤ 500 ms / > 500 ms; CLS ≤ 0.1 / ≤ 0.25 / > 0.25; TBT ≤ 200 ms / ≤ 600 ms / > 600 ms. Lab values and real-user values stay separate, and real-user page and site values stay separate rows; the tested URL, device and Lighthouse version stay in the group heading, and the observation date is shown once per block (lab, real users) instead of on every value.

## States

- **Failed source:** a warning in its group heading ("Search Console needs attention · existing history shown") with the Review link; its charts keep their history.
- **Delayed source:** a quiet note in the group heading.
- **Paused source:** "Paused" label beside the provider; charts unchanged.
- **First import pending:** the group shows its heading with a single line "First import running"; no placeholder charts.
- **All charts of a group hidden:** the group is omitted; the existing "N charts hidden · Show" line remains at the end of the page.
- **No sources:** the existing empty state.

## Events

Event markers stay on the large and small charts. The legend's event count and the header's **Events** link open the events page; clicking a marker (or Enter on a focused chart at a date with events) opens that event there. See [EVENTS_PAGE.md](EVENTS_PAGE.md).

## Responsive

- ≥ 1100 px: six-column grid as above.
- 700–1099 px: large charts take the full width; smalls two per row; paired groups stack.
- < 700 px: summary row becomes a two-column grid; large chart full width at about 160 px; smalls two per row as compact tiles (number, change, sparkline); header controls wrap below the title; period control full width.

## Accessibility

Keep the current canvas labels, keyboard date inspection, live announcements and the daily-values table for every chart. Group headings are h2, chart titles h3. Colour never carries meaning alone: changes keep arrows and signs, quality labels keep words, status cells have text equivalents.

## Configurability (deferred)

Agreed staged plan, to revisit after the default layout ships:

1. Excellent defaults first (this document).
2. Chart menu next to Hide: size (large / small / number only) and move up / down within its group; "Reset layout" in Project settings beside Hidden charts. Stored per project and workspace-wide, like hidden charts (one row per chart with hidden, size and position).
3. Optionally "Apply this layout to other projects in the workspace".
4. Drag and drop only on demand, as an explicit "Edit layout" mode with snapping sizes; move buttons remain the accessible alternative.

Constraints: sizes snap to the grid; charts reorder within their group and groups reorder as blocks.

## Implementation

- Layout rules live in `src/Metrics/ProjectLayout.php` (groups, lead and sizes, grid spans, half-width pairing, polarity, page speed thresholds, value formatting). `src/Livewire/ProjectCharts.php` passes its rows, the project's overview summary and source status counts to `resources/views/components/project-charts.blade.php`, which now also renders the page header.
- Partials: `project-group` (heading, sources, legend, source notices, grid), `metric` (sizes `l`, `s`, `n`), `metric-strip` (monitor status), `page-speed`, `source-notice`, `chart-hide`. `overview-change` takes an optional `polarity`; its classes are `is-good` / `is-bad`.
- Charts stay on Chart.js (`resources/js/app.js`): sizes `l`/`s`/`n`, dashed segment into partial dates with a hollow end point, a shared crosshair by reporting date, and group legends that toggle a series on every chart of the group.
- Differences from the mockup: small charts also draw event markers (otherwise events tied to a second source would appear on no chart); number-tile sparklines are not anchored at zero; daily-value tables list the newest date first; several "no data" charts each get their own line.
- Tests: `tests/Feature/ProjectPageLayoutTest.php`, `tests/Feature/ReportingPeriodTest.php`.

