<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Metribay\Core\Metrics\MetricRegistry;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\Source;

/**
 * A project's events page: a context strip (the main metric line under event lanes), what is coming up, and a
 * timeline grouped by month and day in which same-day deployments are bundled. See docs/EVENTS_PAGE.md.
 */
class EventTimeline
{
    /** Periods of the page; null is all time. */
    public const PERIODS = [7, 30, 90, null];

    /** Event types and the origins they cover, in lane order. */
    public const TYPES = ['release' => ['github_release'], 'content' => ['feed_item', 'sitemap_url'], 'deployment' => ['github_deployment', 'azure_devops_deployment'], 'custom' => ['manual']];

    /** Main line candidates, most telling first. */
    private const LINE_METRICS = ['visits', 'clicks', 'commits', 'downloads', 'published_items', 'pageviews', 'impressions', 'prs_merged'];

    /** Timeline entries (single events, occurrences or bundles) per page. */
    public const PER_PAGE = 60;

    public static function type(string $origin): string
    {
        foreach (self::TYPES as $type => $origins) {
            if (in_array($origin, $origins, true)) {
                return $type;
            }
        }

        return 'deployment';
    }

    /** Event markers (occurrences) of the whole project between two dates, inclusive. */
    public static function countBetween(int $projectId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return ProjectEvent::query()->where('project_id', $projectId)->get()->sum(fn (ProjectEvent $event): int => count($event->occurrencesBetween($from, $to)));
    }

    /**
     * @param  array{type?: string|null, source?: string|null, q?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(Project $project, ?int $days, array $filters, int $page = 1, ?string $focus = null): array
    {
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $events = $this->filtered($project, $filters);
        $from = $days ? $today->subDays($days - 1) : $this->firstDate($project, $events, $today);

        $entries = [];
        $lanes = array_fill_keys(array_keys(self::TYPES), []);
        foreach ($events as $event) {
            $type = self::type($event->origin);
            foreach ($event->occurrencesBetween($from, $today) as $date) {
                $entries[] = ['date' => $date, 'type' => $type, 'event' => $event, 'repeat' => $event->isCustom() && $event->recurrence];
                $lanes[$type][$date][] = $event;
            }
        }

        $entries = $this->bundle($entries);
        usort($entries, fn (array $a, array $b): int => [$b['date'], $this->rank($a), $b['sort']] <=> [$a['date'], $this->rank($b), $a['sort']]);
        $pages = max(1, (int) ceil(count($entries) / self::PER_PAGE));
        if ($focus !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $focus)) {
            // Open the page that holds the date a chart marker pointed to.
            $index = collect($entries)->search(fn (array $entry): bool => $entry['date'] <= $focus);
            $page = $index === false ? $pages : intdiv($index, self::PER_PAGE) + 1;
        }
        $page = max(1, min($pages, $page));

        return [
            'from' => $from->toDateString(), 'to' => $today->toDateString(), 'days' => $days,
            'line' => $this->line($project, $from, $today),
            'lanes' => $this->lanes($lanes, $from, $today),
            'upcoming' => $this->upcoming($events, $today),
            'months' => $this->months(array_slice($entries, ($page - 1) * self::PER_PAGE, self::PER_PAGE)),
            'count' => count($entries), 'page' => $page, 'pages' => $pages,
        ];
    }

    /**
     * @param  array{type?: string|null, source?: string|null, q?: string|null}  $filters
     * @return Collection<int, ProjectEvent>
     */
    private function filtered(Project $project, array $filters): Collection
    {
        $type = $filters['type'] ?? null;
        $source = $filters['source'] ?? null;
        $search = trim((string) ($filters['q'] ?? ''));

        return ProjectEvent::query()->where('project_id', $project->id)
            ->when(isset(self::TYPES[$type]), fn ($query) => $query->whereIn('origin', self::TYPES[$type]))
            ->when($source === 'project', fn ($query) => $query->whereNull('source_id'))
            ->when(is_numeric($source), fn ($query) => $query->where('source_id', (int) $source))
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(title) LIKE ?', ['%'.addcslashes(mb_strtolower($search), '%_\\').'%']))
            ->orderByDesc('date')->orderByDesc('id')->get();
    }

    /**
     * All time starts at the first event or the first recorded metric, at most five years back.
     *
     * @param  Collection<int, ProjectEvent>  $events
     */
    private function firstDate(Project $project, Collection $events, CarbonImmutable $today): CarbonImmutable
    {
        $metrics = DailyMetric::query()->whereIn('source_id', Source::query()->where('project_id', $project->id)->pluck('id'))->min('date');
        $first = collect([$events->min(fn (ProjectEvent $event) => $event->date->toDateString()), $metrics ? CarbonImmutable::parse($metrics)->toDateString() : null])->filter()->min();
        $first = $first ? CarbonImmutable::parse($first) : $today->subDays(29);

        return $first->max($today->subYears(5))->min($today->subDays(6));
    }

    /**
     * Imported events of one data source, origin and environment on the same day collapse into one bundle.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function bundle(array $entries): array
    {
        $groups = [];
        $result = [];
        foreach ($entries as $entry) {
            $event = $entry['event'];
            $entry['sort'] = $event->occurred_at?->getTimestamp() ?? 0;
            if ($entry['type'] === 'custom') {
                $result[] = $entry;

                continue;
            }
            $environment = $entry['type'] === 'deployment' && str_contains($event->title, ' · ') ? strstr($event->title, ' · ', true) : null;
            $groups[$entry['date'].'|'.$event->source_id.'|'.$event->origin.'|'.$environment][] = $entry + ['environment' => $environment];
        }
        foreach ($groups as $group) {
            if (count($group) === 1) {
                $result[] = $group[0];

                continue;
            }
            usort($group, fn (array $a, array $b): int => $b['sort'] <=> $a['sort']);
            $result[] = ['date' => $group[0]['date'], 'type' => $group[0]['type'], 'bundle' => array_column($group, 'event'), 'environment' => $group[0]['environment'],
                'event' => $group[0]['event'], 'repeat' => false, 'sort' => $group[0]['sort']];
        }

        return $result;
    }

    /**
     * Within a day: custom events, then repeats, then imported ones.
     *
     * @param  array{type: string, repeat: bool}  $entry
     */
    private function rank(array $entry): int
    {
        return $entry['type'] === 'custom' ? ($entry['repeat'] ? 1 : 0) : 2;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array{month: string, days: list<array{date: string, entries: list<array<string, mixed>>}>}>
     */
    private function months(array $entries): array
    {
        $months = [];
        foreach ($entries as $entry) {
            $month = substr($entry['date'], 0, 7);
            $months[$month][$entry['date']][] = $entry;
        }

        return array_map(fn (string $month, array $days): array => ['month' => $month,
            'days' => array_map(fn (string $date, array $dayEntries): array => ['date' => $date, 'entries' => $dayEntries], array_keys($days), $days)], array_keys($months), $months);
    }

    /**
     * Future one-off events and every repeating event once, with its next date (or when it ended).
     *
     * @param  Collection<int, ProjectEvent>  $events
     * @return list<array{event: ProjectEvent, date: string|null, ended: bool}>
     */
    private function upcoming(Collection $events, CarbonImmutable $today): array
    {
        $items = [];
        foreach ($events as $event) {
            if ($event->recurrence && $event->isCustom()) {
                $next = $event->nextOccurrence($today->addDay());
                $items[] = ['event' => $event, 'date' => $next, 'ended' => $next === null];
            } elseif ($event->date->gt($today)) {
                $items[] = ['event' => $event, 'date' => $event->date->toDateString(), 'ended' => false];
            }
        }
        usort($items, fn (array $a, array $b): int => [$a['ended'], $a['date'] ?? '', $a['event']->title] <=> [$b['ended'], $b['date'] ?? '', $b['event']->title]);

        return $items;
    }

    /**
     * The first metric with data in the window, from the first data source that has it.
     *
     * @return array{label: string, source: string, path: string}|null
     */
    private function line(Project $project, CarbonImmutable $from, CarbonImmutable $to): ?array
    {
        $sources = Source::query()->where('project_id', $project->id)->orderBy('id')->get();
        $rows = DailyMetric::query()->whereIn('source_id', $sources->pluck('id'))->whereIn('metric', self::LINE_METRICS)
            ->where('date', '>=', $from->toDateString())->where('date', '<=', $to->toDateString())->get(['source_id', 'metric', 'date', 'value']);
        foreach (self::LINE_METRICS as $metric) {
            foreach ($sources as $source) {
                if (! in_array($metric, ProviderCatalog::metricKeys($source->kind), true)) {
                    continue;
                }
                $values = $rows->where('source_id', $source->id)->where('metric', $metric)->mapWithKeys(fn (DailyMetric $row): array => [$row->date->toDateString() => (float) $row->value]);
                if ($values->isEmpty()) {
                    continue;
                }
                $points = [];
                for ($date = $from; $date->lte($to); $date = $date->addDay()) {
                    $points[] = $values->get($date->toDateString());
                }

                return ['label' => __(MetricRegistry::get($metric)->label), 'source' => $source->label, 'path' => $this->path($points, (float) max(1, $values->max()))];
            }
        }

        return null;
    }

    /**
     * An SVG path on a 1000 × 60 canvas; missing dates break the line, and a lone date is drawn as a short dash.
     *
     * @param  list<float|null>  $points
     */
    private function path(array $points, float $max): string
    {
        $path = '';
        $last = count($points) - 1;
        $open = false;
        foreach ($points as $index => $value) {
            if ($value === null) {
                $open = false;

                continue;
            }
            $x = $last > 0 ? 1000 * $index / $last : 500;
            $y = 57 - 54 * $value / $max;
            $path .= ($open ? 'L' : 'M').round($x, 1).' '.round($y, 1);
            if (! $open && ($points[$index + 1] ?? null) === null) {
                $path .= 'h1.5';
            }
            $open = true;
        }

        return $path;
    }

    /**
     * Lane markers positioned as a share of the window; several events of a lane on one day share a marker.
     *
     * @param  array<string, array<string, list<ProjectEvent>>>  $lanes
     * @return list<array{type: string, marks: list<array<string, mixed>>}>
     */
    private function lanes(array $lanes, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $span = max(1, (int) $from->diffInDays($to));
        $result = [];
        foreach ($lanes as $type => $dates) {
            if ($dates === []) {
                continue;
            }
            $marks = [];
            foreach ($dates as $date => $events) {
                $first = $events[0];
                $marks[] = ['date' => $date, 'x' => round(100 * $from->diffInDays(CarbonImmutable::parse($date)) / $span, 3), 'count' => count($events), 'titles' => array_map(fn (ProjectEvent $event): string => $event->title, $events),
                    'color' => $first->displayColor(), 'repeat' => count($events) === 1 && $first->isCustom() && $first->recurrence,
                    'anchor' => count($events) > 1 ? 'day-'.$date : ($first->isCustom() && $first->recurrence ? 'occurrence-'.$first->id.'-'.$date : 'event-'.$first->id)];
            }
            $result[] = ['type' => $type, 'marks' => $marks];
        }

        return $result;
    }
}
