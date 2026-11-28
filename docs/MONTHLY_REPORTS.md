# Monthly reports

One saved report per project and calendar month: what changed, why that is likely, and what to do next.

## Saving and sealing

`metribay:monthly-reports` runs daily at 06:10 UTC. On the 1st through the 4th the closed month is refreshed, so late collection can still land. From the 5th it is sealed, together with any older month that was left open, so a missed run is caught up the next day. A sealed report never changes. `--month=YYYY-MM` writes and seals one closed month; `--backfill` every closed month that has daily data. The current month is never written. The sample portfolio seals a report for every closed month when it is stored (`DemoData`).

Reports are workspace-scoped, deleted with the project and moved with it to another workspace. A payload keeps the labels it was saved with. Payloads carry `version` (2); the page still reads version 1.

## What a report holds

Dates are each data source's own reporting dates, in its timezone. For every metric: the month, the month before, the same month a year ago, the month before that one (for the season), the average of the three previous months and the average of the two months before last.

- A missing day is unknown, not zero. A stored zero stays zero. A sum covers the days with rows.
- Visitors and active users are never summed from days. They are the provider's total for that exact calendar month (`period_metrics`, start on the 1st, end on the last day). Analytics connectors read closed months after they end (see below); without that row the figure is unknown.
- A month is partial when days are missing or provisional, when the source's data starts after the 1st, or, for a provider total, when the source's daily visits have gaps that month. Partial comparison months are marked too (`previous_partial`, `year_partial`). The 3-month average skips months with less than 90% of their days.
- Month length is marked (`unequal_days`) only for totals that differ by more than 5% in length, such as February against January. Rates and point-in-time figures are never marked for length.
- Parts of ratios (bounces, total visit duration) and status codes are not stored as figures.

Each data source also records the month's health (sync runs and failed runs in the month, whether the latest run up to a week after the month failed, days with downtime, approximate minutes down and the lowest uptime), the date its data starts when that falls inside the month, the last data date when collection was paused during the month, and a chart: its lead metric (the first one in the project page group order) for each of the thirteen months up to the report month.

Events are notes people add and releases, one per date and title; deployments, feed items and sitemap URLs are counted but not listed. For each one-off event in the month and the month before, the snapshot keeps the metrics whose daily average moved by 15% or more between the week before and the week from the event. Small daily counts must also move by three standard errors.

`breakdown_months` keeps a calendar month of each audience breakdown. Channel totals are summed from `channel_days` and follow late data until the report is sealed. Connectors archive the other lists for closed, final months (`CollectionResult::$breakdownMonths`), written once. The rolling 7/30/90-day lists stay in `breakdown_rows`.

Top queries are the month's archived queries with their clicks the month before; queries that lost the most clicks are kept separately.

## The summary and actions

`MonthlyReportRules` turns a payload into at most three sentences and three actions, in the reader's language, on every view.

1. **Headline.** The largest move against the month before in the most important tier that has one: outcomes first (revenue, recurring revenue, subscribers, orders, conversions, key events), then audience (visitors, visits, active users, search clicks, downloads, followers), then anything else. A move must be material: 10% (5% for money and subscribers, 25% outside the two tiers), at least 20 counts (50 outside the tiers), 10 points for a performance score. Rates, uptime, rolling field data and counts of work (open issues, lines changed) never lead.
2. **Context.** The first that applies: the month before was the exception (it stood 30% off the two months before it and this month went at least halfway back, with the month before's event if one moved this figure); an event whose week after moved this figure the same way; one channel, referrer or landing page that carries much more of the change than its share of traffic; the same two months last year moved the same way (seasonal); otherwise the change against the same month last year.
3. **Second figure or problem.** Failed or stopped collection and downtime come before a second figure: an outcome or audience move in the other direction, else the next largest from another family.

Actions: reconnect a failing source, turn a paused one back on, find the cause of downtime, look into a bad move (named event, channel, the query that lost most clicks, or the drop itself, unless it was the exception or seasonal), check a page whose lab score fell 10 points or whose LCP or blocking time rose by a quarter, and host items. "Likely" appears only where a cause is suggested. A comparison is null where it would mislead: a zero or unknown base, a provider total for part of a month, less than half a month known. Totals over months of different length, or with missing days, are compared per day and the page marks them with †.

## Page

`GET /w/{workspace}/projects/{project}/reports/{month?}`; without a month, the newest report. `?month=YYYY-MM` (the picker's form) redirects to that month. The page shows, in order: the month picker with previous and next, **Explore this month** (the project page for that month), the summary, up to six key figures with their change against the month before and the same month last year, actions, listed events, and per data source its monthly bars and a table (value, change against the month before, the same month last year and the 3-month average, with the compared figure). Phones stack each row. Small counts show the difference instead of a percentage. Footnotes explain the dash, * and †. Read-only.

## Host contracts

`MonthlyReportContributions::capture()` freezes host items into the payload: translation keys and parameters, rendered in the reader's language. `MonthlyReportNarrative::rank()` may return candidate ids from `MonthlyReportRules::candidates()` in another order. It never supplies text, and collection and uptime problems are always kept. Core binds no-op defaults.
