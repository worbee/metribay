# Events page

Status: proposed by Rob and released in Core 0.9.0 (2026-11-03). Replaces the event list that sat under the project page's charts.

## Why

Metrics are read at a glance; events are a log people scan, filter and edit. The full list under the charts had no paging, so a project with imported deployments (a real one has 312 events) ended in a very long tail below the charts. The project page keeps every event marker on its charts, so moving the list to its own page does not break the project page's "show everything" rule (docs/PROJECT_PAGE.md).

## Project page

- The header has an **Events** link next to **Add event**, with the number of event markers (occurrences) in the selected period.
- The "3 events" item in each group legend links to the same page.
- Clicking an event marker on a large or small chart (within 6 px of it), or pressing Enter on a focused chart whose cursor date has events, opens the events page with `?days={period}&date={date}`: one event opens at `#event-{id}` (a repeat at `#occurrence-{id}-{date}`), several at `#day-{date}`. The `date` parameter picks the timeline page that holds that date.
- **Add event** still opens the add dialog on the project page.

## Events page

Route `metribay.events.index`, `GET /w/{workspace}/projects/{project}/events`, for everyone who can view the project. Query: `days` (7, 30, 90 or `all`, default 30; anything else is 422), `type` (`release`, `deployment`, `custom`), `source` (`project` for whole-project events or a data source ID of this project), `q` (title search, case-insensitive), `page`, `date`. Built by `Services\EventTimeline`; view `events.blade.php`.

1. **Header**: back link to the project (same period), "Events", the number of timeline entries and the date range, and the period switch with **All**. All time starts at the first event or first recorded metric, at most five years back.
2. **Context strip**: one lane per event type with events in the window (**Releases**, **Deployments**, **Custom**) above the period's main line. The line is the first metric with data from visits, clicks, commits, downloads, page views, impressions, PRs merged, taken from the first data source that has it, drawn as an SVG path with gaps for missing dates. Marks use the chart shapes (dot in the custom colour, grey diamond for imported events, lighter dot for repeats); several events of a lane on one day share a mark with a count. Marks link to the event, occurrence or day below.
3. **Upcoming**: future one-off events and every repeating custom event once ("Monthly newsletter · repeats monthly · next 30 Nov"); a series that ended shows "Ended :date" at the end of the list. This is where a series is edited.
4. **Timeline**: newest first, grouped by month (sticky heading) and day. Within a day: custom events, then repeats, then imported events. Repeats are lighter entries with a **Series** link and are not editable on their own. Imported events of one data source, origin and environment on the same day (two or more) collapse into one expandable row such as "4 deployments · production", newest first inside; the environment is the part of the title before " · ". Imported titles link to the provider. 60 entries (a bundle counts once) per page, with Newer/Older links.
5. **Filters**: type, data source and title search (GET form; selects submit on change). Filters apply to the strip, Upcoming and the timeline.
6. **Adding and editing**: a quick-add row at the top of the timeline (date and title; **More options** opens the full dialog) and one edit dialog per custom event on the page, which also holds **Delete event**. These dialogs replace the old inline "Edit event" fold-outs. Every event form posts `_dialog`; old input is only used by the form that was sent, and a dialog that comes back with errors reopens (`data-open-on-load`).

Linking to an event inside a collapsed bundle opens the bundle, scrolls the event into view and highlights it briefly (`revealTarget` in `resources/js/app.js`).

## Tests

`tests/Feature/EventsPageTest.php` (bundling, Upcoming and repeats, filters, period, paging and date focus, strip lanes and line, dialog errors and quick add) and `tests/Feature/ProjectEventTest.php` (Events link and period count, marker anchors and the events URL in chart configs).
