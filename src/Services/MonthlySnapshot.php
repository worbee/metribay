<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Metribay\Core\Contracts\MonthlyReportContributions;
use Metribay\Core\Metrics\MetricDefinition;
use Metribay\Core\Metrics\MetricRegistry;
use Metribay\Core\Metrics\ProjectLayout;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\MonthlyReport;
use Metribay\Core\Models\PeriodMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\SearchQueryMonth;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\SyncRun;
use Metribay\Core\Models\Workspace;

/**
 * Builds the frozen figures for one project and calendar month. Missing values stay null.
 * A sealed report is returned unchanged, so later collection cannot rewrite it. See docs/MONTHLY_REPORTS.md.
 */
final class MonthlySnapshot
{
    public const VERSION = 2;

    /** Events a report lists: notes people add and releases. Deployments, feed items and sitemap URLs are too frequent to explain a month. */
    public const EVENT_ORIGINS = ['manual', 'github_release'];

    /** Stored parts of ratios and status codes: not figures anyone reads on their own. */
    public const OMITTED = ['bounces', 'visit_duration', 'monitor_status'];

    /** Metrics whose days exist only when something happened (a post, a new URL): a missing day is not a gap in collection. */
    private const SPARSE = ['published_items', 'new_urls'];

    /** Daily metrics compared around an event: the week before it against the week from it. */
    private const SHIFT_KEYS = ['paddle_revenue', 'store_revenue', 'store_orders', 'conversions', 'key_events', 'visits', 'pageviews', 'clicks', 'impressions', 'downloads', 'followers', 'response_time_24h', 'lab_score', 'lab_lcp', 'lab_tbt'];

    private const SHIFT_DAYS = 7;

    private const SHIFT_MIN_DAYS = 4;

    private const SHIFT_MIN_CHANGE = 0.15;

    /** A month counts towards the 3-month average when at least this share of its days is known. */
    private const TRAILING_COVERAGE = 0.9;

    public function __construct(private readonly BreakdownArchive $archive) {}

    /** Writes or refreshes the month. $seal freezes it. A month already sealed is not touched. */
    public function store(Workspace $workspace, Project $project, string $month, bool $seal, ?CarbonImmutable $now = null): MonthlyReport
    {
        $now ??= CarbonImmutable::now('UTC');
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth()->toDateString();

        return DB::transaction(function () use ($workspace, $project, $month, $seal, $now, $start): MonthlyReport {
            $existing = MonthlyReport::query()->where('project_id', $project->id)->whereDate('month', $start)->lockForUpdate()->first();
            if ($existing instanceof MonthlyReport && $existing->sealed_at !== null) {
                return $existing;
            }
            $payload = $this->payload($workspace, $project, $month, $now);
            $values = ['payload' => $payload, 'has_data' => $this->hasData($payload), 'sealed_at' => $seal ? $now : null];
            if ($existing instanceof MonthlyReport) {
                $existing->fill($values)->save();

                return $existing;
            }

            return MonthlyReport::query()->create(['project_id' => $project->id, 'month' => $start] + $values);
        });
    }

    /** @return array<string, mixed> */
    public function payload(Workspace $workspace, Project $project, string $month, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth()->startOfDay();
        $previousStart = $start->subMonth()->startOfMonth();
        [$events, $eventCount] = $this->events($project, $start, $end);
        [$previousEvents] = $this->events($project, $previousStart, $previousStart->endOfMonth()->startOfDay());
        $sources = [];
        $shifts = [];
        foreach ($project->sources()->orderBy('id')->get() as $source) {
            [$sources[], $found] = $this->source($source, $start, $end, [...$events, ...$previousEvents]);
            foreach ($found as $index => $list) {
                $shifts[$index] = [...($shifts[$index] ?? []), ...$list];
            }
        }
        foreach ([...$events, ...$previousEvents] as $index => $event) {
            $list = $shifts[$index] ?? [];
            usort($list, fn (array $a, array $b): int => abs($b['after'] / $b['before'] - 1) <=> abs($a['after'] / $a['before'] - 1));
            if ($index < count($events)) {
                $events[$index]['shifts'] = array_slice($list, 0, 8);
            } else {
                $previousEvents[$index - count($events)]['shifts'] = array_slice($list, 0, 8);
            }
        }
        [$queries, $losses] = $this->queries($project, $start);

        return [
            'version' => self::VERSION,
            'month' => $start->format('Y-m'),
            'computed_at' => $now->utc()->toIso8601String(),
            'sources' => $sources,
            'events' => $events,
            'event_count' => $eventCount,
            'previous_events' => $previousEvents,
            'queries' => $queries,
            'query_losses' => $losses,
            'contributions' => $this->contributions($workspace, $project, $month),
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function hasData(array $payload): bool
    {
        foreach ($payload['sources'] as $source) {
            foreach ($source['metrics'] as $metric) {
                if ($metric['value'] !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{0: array<string, mixed>, 1: array<int, list<array{source_id: int, key: string, before: float, after: float}>>}
     */
    private function source(Source $source, CarbonImmutable $start, CarbonImmutable $end, array $events): array
    {
        $month = $start->format('Y-m');
        $previousMonth = $start->subMonth()->format('Y-m');
        // The month under report follows late collection until it is sealed; the month before is closed already.
        $this->archive->channels($source, $month, true);
        $this->archive->channels($source, $previousMonth);
        $from = $start->subMonths(14)->startOfMonth();
        // Plain rows: a year of daily values per metric is too many models to hydrate for every month.
        $rows = DailyMetric::query()->where('source_id', $source->id)->where('date', '>=', $from->toDateString())
            ->where('date', '<=', $end->addDays(self::SHIFT_DAYS)->toDateString())->orderBy('date')->toBase()->get(['metric', 'date', 'value', 'is_partial', 'context']);
        $all = $this->series($rows);
        $last = $end->toDateString();
        $daily = array_map(fn (array $list): array => array_values(array_filter($list, fn (array $row): bool => $row['date'] <= $last)), $all);
        $periods = [];
        foreach (PeriodMetric::query()->where('source_id', $source->id)->where('start_date', '>=', $from->toDateString())->get() as $period) {
            $periods[$period->metric][$period->start_date->toDateString().'|'.$period->end_date->toDateString()] = ['value' => (float) $period->value, 'partial' => (bool) $period->is_partial];
        }
        $first = $this->date(DailyMetric::query()->where('source_id', $source->id)->min('date'));
        $last = $this->date(DailyMetric::query()->where('source_id', $source->id)->max('date'));
        $added = $first !== null && $first > $start->toDateString() && $first <= $end->toDateString() ? $first : null;
        $paused = ! $source->enabled && $last !== null && $last >= $start->toDateString() && $last < $end->toDateString() ? $last : null;
        $windows = [
            'value' => [$start, $end],
            'previous' => $this->monthWindow($start->subMonth()),
            'year_ago' => $this->monthWindow($start->subYear()),
            'year_ago_previous' => $this->monthWindow($start->subYear()->subMonth()),
        ];
        $trailing = [];
        for ($i = 1; $i <= 3; $i++) {
            $trailing[] = $this->monthWindow($start->subMonths($i));
        }
        // A provider total for a month with missing days in the source's own daily figures (a tracker outage) is partial too.
        $gaps = [];
        foreach ([...array_values($windows), ...$trailing] as [$windowStart, $windowEnd]) {
            $known = count(array_unique(array_column(array_filter($daily['visits'] ?? $daily['active_users'] ?? [], fn (array $row): bool => $row['date'] >= $windowStart->toDateString() && $row['date'] <= $windowEnd->toDateString()), 'date')));
            $gaps[$windowStart->toDateString()] = ($daily['visits'] ?? $daily['active_users'] ?? []) !== [] && $known > 0 && $known < $windowStart->daysInMonth;
        }
        $metrics = [];
        $figures = [];
        foreach (MetricRegistry::all() as $definition) {
            if (in_array($definition->key, self::OMITTED, true)) {
                continue;
            }
            $figure = $this->figure($definition, $daily, $periods, $windows, $trailing, $first, $gaps);
            if ($figure === null) {
                continue;
            }
            $figure['comparability'] = $this->flags($figure);
            $metrics[] = $figure;
            $figures[$definition->key] = $figure;
        }
        $chart = $this->chart($figures, $daily, $periods, $start, $first);
        $breakdowns = $this->archive->forMonth($source, $month);
        $previousBreakdowns = $this->archive->forMonth($source, $previousMonth);

        return [[
            'id' => $source->id,
            'kind' => $source->kind,
            'provider' => (string) (ProviderCatalog::all()[$source->kind]['label'] ?? $source->kind),
            'label' => $source->label,
            'timezone' => $source->reporting_timezone ?: 'UTC',
            'enabled' => (bool) $source->enabled,
            'first_date' => $first,
            'added_on' => $added,
            'paused_on' => $paused,
            'health' => $this->health($source, $start, $end, array_values(array_filter($daily['uptime_24h'] ?? [], fn (array $row): bool => $row['date'] >= $start->toDateString()))),
            'metrics' => $metrics,
            'chart' => $chart,
            'channels' => $this->channelList($breakdowns),
            'previous_channels' => $this->channelList($previousBreakdowns),
            'breakdowns' => $breakdowns,
            'previous_breakdowns' => $previousBreakdowns,
        ], $this->shifts($source, $all, $events)];
    }

    /**
     * Daily rows per metric as plain values in date order: windows are compared as date strings.
     *
     * @param  Collection<int, \stdClass>  $rows
     * @return array<string, list<array{date: string, value: float, partial: bool, currency: ?string}>>
     */
    private function series(Collection $rows): array
    {
        $series = [];
        foreach ($rows as $row) {
            $context = is_string($row->context) ? json_decode($row->context, true) : null;
            $currency = is_array($context) ? ($context['currency'] ?? null) : null;
            $series[(string) $row->metric][] = ['date' => substr((string) $row->date, 0, 10), 'value' => (float) $row->value, 'partial' => (bool) $row->is_partial, 'currency' => is_string($currency) ? $currency : null];
        }

        return $series;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function monthWindow(CarbonImmutable $day): array
    {
        $start = $day->startOfMonth();

        return [$start, $start->endOfMonth()->startOfDay()];
    }

    private function date(mixed $value): ?string
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_string($value) && $value !== '' => substr($value, 0, 10),
            default => null,
        };
    }

    /**
     * @param  array<string, list<array{date: string, value: float, partial: bool, currency: ?string}>>  $daily
     * @param  array<string, array<string, array{value: float, partial: bool}>>  $periods
     * @param  array<string, array{0: CarbonImmutable, 1: CarbonImmutable}>  $windows
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $trailing
     * @param  array<string, bool>  $gaps  Months (by first date) whose daily visits have missing days.
     * @return array<string, mixed>|null
     */
    private function figure(MetricDefinition $definition, array $daily, array $periods, array $windows, array $trailing, ?string $first, array $gaps = []): ?array
    {
        $results = [];
        foreach ($windows as $name => [$from, $to]) {
            $results[$name] = $this->measure($definition, $daily, $periods, $from, $to, $first, $gaps[$from->toDateString()] ?? false);
        }
        $trail = [];
        foreach ($trailing as $index => [$from, $to]) {
            $result = $this->measure($definition, $daily, $periods, $from, $to, $first, $gaps[$from->toDateString()] ?? false);
            if ($result['value'] !== null && (! $result['partial'] || ($definition->aggregation !== 'period' && $result['coverage'] >= self::TRAILING_COVERAGE))) {
                $trail[$index] = $result['value'];
            }
        }
        // The two months before the previous one: what was normal before last month, when last month was unusual.
        $baseline = isset($trail[1], $trail[2]) ? ($trail[1] + $trail[2]) / 2 : null;
        $monthStart = $windows['value'][0]->toDateString();
        $dailyInMonth = $definition->aggregation === 'period' && array_filter($daily[$definition->key] ?? [], fn (array $row): bool => $row['date'] >= $monthStart) !== [];
        if ($results['value']['value'] === null && $results['previous']['value'] === null && $results['year_ago']['value'] === null && $trail === [] && ! $dailyInMonth) {
            return null;
        }
        $currency = $results['value']['currency'];
        if ($definition->unit === 'currency') {
            // A sum in another currency, or in none, is not comparable.
            foreach ($results as $name => $result) {
                if ($result['value'] !== null && ($result['currency'] === null || ($currency !== null && $result['currency'] !== $currency))) {
                    $results[$name]['value'] = null;
                }
            }
        }
        $figure = ['key' => $definition->key, 'unit' => $definition->unit, 'aggregation' => $definition->ratio !== null ? 'ratio' : $definition->aggregation, 'currency' => $currency];
        foreach (['value' => '', 'previous' => 'previous_', 'year_ago' => 'year_', 'year_ago_previous' => 'year_previous_'] as $name => $prefix) {
            $figure[$name] = $results[$name]['value'];
            $figure[($prefix === '' ? 'days_' : $prefix).'known'] = $results[$name]['known'];
            $figure[$prefix === '' ? 'days_in_month' : $prefix.'days'] = $windows[$name][0]->daysInMonth;
            $figure[$prefix.'partial'] = $results[$name]['value'] !== null && $results[$name]['partial'];
        }
        $figure['trailing_average'] = $trail === [] ? null : array_sum($trail) / count($trail);
        $figure['trailing_months'] = count($trail);
        $figure['baseline'] = $baseline;

        return $figure;
    }

    /**
     * One calendar month of a metric. Coverage is the share of the month's days known to the provider.
     *
     * @param  array<string, list<array{date: string, value: float, partial: bool, currency: ?string}>>  $daily
     * @param  array<string, array<string, array{value: float, partial: bool}>>  $periods
     * @return array{value: ?float, partial: bool, known: int, currency: ?string, coverage: float}
     */
    private function measure(MetricDefinition $definition, array $daily, array $periods, CarbonImmutable $from, CarbonImmutable $to, ?string $first, bool $gap = false): array
    {
        $days = $from->daysInMonth;
        $startDate = $from->toDateString();
        $endDate = $to->toDateString();
        $none = ['value' => null, 'partial' => true, 'known' => 0, 'currency' => null, 'coverage' => 0.0];
        $beforeData = $first === null || $first > $startDate;
        if ($definition->ratio !== null) {
            [$numerator, $denominator, $scale] = $definition->ratio;
            [$value, $partial, $known] = $this->ratioWindow($daily[$numerator] ?? [], $daily[$denominator] ?? [], $scale, $definition->unit, $startDate, $endDate);

            return ['value' => $value, 'partial' => $partial || $known < $days, 'known' => $known, 'currency' => null, 'coverage' => $known / $days];
        }
        if ($definition->aggregation === 'period') {
            $row = $periods[$definition->key][$startDate.'|'.$endDate] ?? null;
            if ($row === null) {
                return $none;
            }
            $partial = $row['partial'] || $beforeData || $gap;

            return ['value' => $row['value'], 'partial' => $partial, 'known' => $days, 'currency' => null, 'coverage' => $partial ? 0.0 : 1.0];
        }
        $inWindow = array_values(array_filter($daily[$definition->key] ?? [], fn (array $row): bool => $row['date'] >= $startDate && $row['date'] <= $endDate));
        if ($inWindow === []) {
            return $none;
        }
        $currencies = array_values(array_unique(array_map(fn (array $row): string => $row['currency'] ?? '', $inWindow)));
        $currency = count($currencies) === 1 && $currencies[0] !== '' ? $currencies[0] : null;
        if ($definition->aggregation === 'sum' && count($currencies) > 1) {
            return $none;
        }
        $known = count(array_unique(array_column($inWindow, 'date')));
        $provisional = in_array(true, array_column($inWindow, 'partial'), true);
        if ($definition->aggregation === 'latest') {
            $last = $inWindow[count($inWindow) - 1];

            return ['value' => $last['value'], 'partial' => $provisional || $last['date'] !== $endDate, 'known' => $known, 'currency' => $currency, 'coverage' => $known / $days];
        }
        $sparse = in_array($definition->key, self::SPARSE, true);
        $partial = $provisional || ($sparse ? $beforeData : $known < $days);
        $coverage = $sparse ? ($beforeData ? 0.0 : 1.0) : $known / $days;

        return ['value' => (float) array_sum(array_column($inWindow, 'value')), 'partial' => $partial, 'known' => $known, 'currency' => $currency, 'coverage' => $coverage];
    }

    /**
     * @param  list<array{date: string, value: float, partial: bool, currency: ?string}>  $numerators
     * @param  list<array{date: string, value: float, partial: bool, currency: ?string}>  $denominators
     * @return array{0: ?float, 1: bool, 2: int}
     */
    private function ratioWindow(array $numerators, array $denominators, float $scale, string $unit, string $start, string $end): array
    {
        $byDate = [];
        foreach ($denominators as $row) {
            if ($row['date'] >= $start && $row['date'] <= $end) {
                $byDate[$row['date']]['den'] = ($byDate[$row['date']]['den'] ?? 0) + $row['value'];
                $byDate[$row['date']]['partial'] = ($byDate[$row['date']]['partial'] ?? false) || $row['partial'];
            }
        }
        foreach ($numerators as $row) {
            if (isset($byDate[$row['date']])) {
                $byDate[$row['date']]['num'] = ($byDate[$row['date']]['num'] ?? 0) + $row['value'];
                $byDate[$row['date']]['partial'] = $byDate[$row['date']]['partial'] || $row['partial'];
            }
        }
        $numerator = 0.0;
        $denominator = 0.0;
        $partial = false;
        $known = 0;
        foreach ($byDate as $day) {
            if (! isset($day['num'])) {
                continue;
            }
            $numerator += $day['num'];
            $denominator += $day['den'];
            $partial = $partial || $day['partial'];
            $known++;
        }
        if ($denominator <= 0) {
            return [null, true, $known];
        }
        $value = round($numerator / $denominator * $scale, 6);

        return [$unit === 'percent' ? min(100.0, $value) : $value, $partial, $known];
    }

    /**
     * Why a comparison may not be fair. Month length only matters for totals, and only when it differs by more than 5%.
     *
     * @param  array<string, mixed>  $figure
     * @return list<string>
     */
    private function flags(array $figure): array
    {
        $flags = [];
        if ($figure['value'] !== null && $figure['partial']) {
            $flags[] = 'partial';
        }
        if ($figure['previous_partial']) {
            $flags[] = 'previous_partial';
        }
        if ($figure['year_partial']) {
            $flags[] = 'year_partial';
        }
        if ($figure['aggregation'] === 'sum' && $figure['previous'] !== null && abs($figure['days_in_month'] - $figure['previous_days']) / $figure['days_in_month'] > 0.05) {
            $flags[] = 'unequal_days';
        }
        if ($figure['trailing_average'] !== null && $figure['trailing_months'] < 3) {
            $flags[] = 'trailing_incomplete';
        }

        return $flags;
    }

    /**
     * The source's lead metric, month by month for the year up to the report month: the order of the project page groups.
     *
     * @param  array<string, array<string, mixed>>  $figures
     * @param  array<string, list<array{date: string, value: float, partial: bool, currency: ?string}>>  $daily
     * @param  array<string, array<string, array{value: float, partial: bool}>>  $periods
     * @return array{key: string, months: list<array{month: string, value: ?float, partial: bool}>}|null
     */
    private function chart(array $figures, array $daily, array $periods, CarbonImmutable $start, ?string $first): ?array
    {
        $key = null;
        foreach (array_merge(...array_values(ProjectLayout::GROUPS)) as $candidate) {
            $figure = $figures[$candidate] ?? null;
            if ($figure !== null && $figure['value'] !== null && $figure['aggregation'] !== 'period' && $figure['unit'] !== 'status') {
                $key = $candidate;
                break;
            }
        }
        if ($key === null) {
            foreach ($figures as $candidate => $figure) {
                if ($figure['value'] !== null && $figure['aggregation'] !== 'period') {
                    $key = $candidate;
                    break;
                }
            }
        }
        if ($key === null) {
            return null;
        }
        $definition = MetricRegistry::get($key);
        $months = [];
        for ($i = 12; $i >= 0; $i--) {
            [$from, $to] = $this->monthWindow($start->subMonths($i));
            $result = $this->measure($definition, $daily, $periods, $from, $to, $first);
            $months[] = ['month' => $from->format('Y-m'), 'value' => $result['value'], 'partial' => $result['value'] !== null && $result['partial']];
        }

        return ['key' => $key, 'months' => $months];
    }

    /**
     * Collection and uptime in the month itself, not on the day the report was saved.
     *
     * @param  list<array{date: string, value: float, partial: bool, currency: ?string}>  $uptime  The month's uptime observations.
     * @return array{runs: int, failed_runs: int, failing: bool, down_days: int, downtime_minutes: ?float, lowest_uptime: ?float, lowest_uptime_date: ?string}
     */
    private function health(Source $source, CarbonImmutable $start, CarbonImmutable $end, array $uptime): array
    {
        $runs = SyncRun::query()->where('source_id', $source->id)->where('started_at', '>=', $start)->where('started_at', '<', $end->addDay())->pluck('status');
        // A connection still failing a week after the month ended needs fixing; one that recovered does not.
        $latest = SyncRun::query()->where('source_id', $source->id)->where('started_at', '<', $end->addDays(8))->whereIn('status', ['success', 'failed'])->orderByDesc('started_at')->value('status');
        $down = array_values(array_filter($uptime, fn (array $row): bool => $row['value'] < 100.0));
        usort($down, fn (array $a, array $b): int => $a['value'] <=> $b['value']);

        return [
            'runs' => $runs->count(),
            'failed_runs' => $runs->filter(fn (mixed $status): bool => $status === 'failed')->count(),
            'failing' => $latest === 'failed',
            'down_days' => count(array_unique(array_column($down, 'date'))),
            'downtime_minutes' => $uptime === [] ? null : round(array_sum(array_map(fn (array $row): float => (100.0 - $row['value']) / 100.0 * 1440.0, $down)), 1),
            'lowest_uptime' => $down[0]['value'] ?? null,
            'lowest_uptime_date' => $down[0]['date'] ?? null,
        ];
    }

    /**
     * Daily averages in the week before and the week from each one-off event, where they moved by 15% or more.
     *
     * @param  array<string, list<array{date: string, value: float, partial: bool, currency: ?string}>>  $series  Daily values up to a week after the month.
     * @param  list<array<string, mixed>>  $events
     * @return array<int, list<array{source_id: int, key: string, before: float, after: float}>>
     */
    private function shifts(Source $source, array $series, array $events): array
    {
        $byKey = [];
        foreach (self::SHIFT_KEYS as $key) {
            $sum = MetricRegistry::get($key)->aggregation === 'sum';
            foreach ($series[$key] ?? [] as $row) {
                $byKey[$key][$row['date']] = $sum ? ($byKey[$key][$row['date']] ?? 0.0) + $row['value'] : $row['value'];
            }
        }
        $found = [];
        foreach ($events as $index => $event) {
            if ($event['recurring']) {
                continue;
            }
            $day = CarbonImmutable::parse($event['date']);
            $beforeFrom = $day->subDays(self::SHIFT_DAYS)->toDateString();
            $beforeTo = $day->subDay()->toDateString();
            $afterFrom = $day->toDateString();
            $afterTo = $day->addDays(self::SHIFT_DAYS - 1)->toDateString();
            foreach ($byKey as $key => $values) {
                $before = array_filter($values, fn (string $date): bool => $date >= $beforeFrom && $date <= $beforeTo, ARRAY_FILTER_USE_KEY);
                $after = array_filter($values, fn (string $date): bool => $date >= $afterFrom && $date <= $afterTo, ARRAY_FILTER_USE_KEY);
                if (count($before) < self::SHIFT_MIN_DAYS || count($after) < self::SHIFT_MIN_DAYS) {
                    continue;
                }
                $beforeAverage = array_sum($before) / count($before);
                $afterAverage = array_sum($after) / count($after);
                if ($beforeAverage <= 0 || abs($afterAverage / $beforeAverage - 1) < self::SHIFT_MIN_CHANGE) {
                    continue;
                }
                // Counts wander by chance: a small daily count must move by three standard errors to count as a shift.
                if (MetricRegistry::get($key)->unit === 'count' && abs($afterAverage - $beforeAverage) < 3 * sqrt($beforeAverage / count($before) + $afterAverage / count($after))) {
                    continue;
                }
                $found[$index][] = ['source_id' => $source->id, 'key' => $key, 'before' => round($beforeAverage, 4), 'after' => round($afterAverage, 4)];
            }
        }

        return $found;
    }

    /**
     * @param  list<array{dimension: string, partial: bool, items: list<array{item: string, visits: ?float, visitors: ?float, conversions: ?float}>}>  $breakdowns
     * @return list<array{channel: string, visits: float}>
     */
    private function channelList(array $breakdowns): array
    {
        foreach ($breakdowns as $report) {
            if ($report['dimension'] !== 'channel') {
                continue;
            }
            $channels = [];
            foreach ($report['items'] as $item) {
                // Umami counts channels as visitors; either way it is the same unit in both months.
                $value = $item['visits'] ?? $item['visitors'];
                if ($value !== null) {
                    $channels[] = ['channel' => $item['item'], 'visits' => (float) $value];
                }
            }

            return $channels;
        }

        return [];
    }

    /**
     * Listed events of the month, one per date and title, in date order, and the number of all events.
     *
     * @return array{0: list<array{date: string, title: string, origin: string, recurring: bool}>, 1: int}
     */
    private function events(Project $project, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $events = [];
        $seen = [];
        $all = 0;
        $query = ProjectEvent::query()->where('project_id', $project->id)
            ->where(fn ($query) => $query->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orWhereNotNull('recurrence'));
        foreach ($query->orderBy('date')->get() as $event) {
            foreach ($event->occurrencesBetween($start, $end) as $date) {
                $all++;
                $title = trim((string) $event->title);
                $key = $date.'|'.mb_strtolower($title);
                if (! in_array($event->origin, self::EVENT_ORIGINS, true) || $title === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $events[] = ['date' => $date, 'title' => $title, 'origin' => (string) $event->origin, 'recurring' => in_array($event->recurrence, ProjectEvent::RECURRENCES, true)];
            }
        }
        usort($events, fn (array $a, array $b): int => [$a['date'], $a['title']] <=> [$b['date'], $b['title']]);

        return [$events, $all];
    }

    /**
     * The month's top queries and the queries that lost the most clicks against the month before.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function queries(Project $project, CarbonImmutable $start): array
    {
        $ids = $project->sources()->pluck('id');
        $month = SearchQueryMonth::query()->whereIn('source_id', $ids)->whereDate('month', $start->toDateString())->with('rows')->get();
        $previous = SearchQueryMonth::query()->whereIn('source_id', $ids)->whereDate('month', $start->subMonth()->startOfMonth()->toDateString())->with('rows')->get()->keyBy('source_id');
        $rows = [];
        foreach ($month as $archive) {
            $before = [];
            foreach ($previous->get($archive->source_id)->rows ?? [] as $row) {
                $before[(string) $row->query] = (float) $row->clicks;
            }
            foreach ($archive->rows as $row) {
                $query = (string) $row->query;
                $rows[] = [
                    'source_id' => (int) $archive->source_id, 'query' => $query, 'clicks' => (float) $row->clicks,
                    'impressions' => (float) $row->impressions, 'position' => $row->position === null ? null : (float) $row->position,
                    'previous_clicks' => $before[$query] ?? null,
                ];
            }
        }
        usort($rows, fn (array $a, array $b): int => $b['clicks'] <=> $a['clicks']);
        // Only queries listed in both months: a query missing from the archive has unknown clicks, not zero.
        $losses = array_values(array_filter($rows, fn (array $row): bool => $row['previous_clicks'] !== null && $row['previous_clicks'] >= 20 && $row['clicks'] < $row['previous_clicks'] * 0.8));
        usort($losses, fn (array $a, array $b): int => ($b['previous_clicks'] - $b['clicks']) <=> ($a['previous_clicks'] - $a['clicks']));

        return [array_slice($rows, 0, 8), array_slice($losses, 0, 3)];
    }

    /** @return list<array<string, mixed>> */
    private function contributions(Workspace $workspace, Project $project, string $month): array
    {
        $items = app(MonthlyReportContributions::class)->capture($workspace, $project, $month);
        $stored = [];
        // The contract fixes the shape: translation keys and scalar parameters, never free text.
        foreach (array_slice($items, 0, 8) as $index => $item) {
            $stored[] = [
                'id' => $item['key'].':'.$index, 'key' => $item['key'], 'title_key' => $item['title_key'], 'detail_key' => $item['detail_key'],
                'evidence_key' => $item['evidence_key'], 'params' => $item['params'], 'weight' => $item['weight'],
            ];
        }

        return $stored;
    }
}
