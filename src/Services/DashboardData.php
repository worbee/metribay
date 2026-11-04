<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Metribay\Core\Metrics\MetricRegistry;
use Metribay\Core\Metrics\Period;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\PeriodMetric;
use Metribay\Core\Models\Project;

class DashboardData
{
    /** Overview columns in display order, matched by metric capability rather than provider. */
    public const CATEGORIES = ['traffic' => 'visits', 'search' => 'clicks', 'development' => 'commits', 'issues' => 'open_issues', 'downloads' => 'downloads', 'uptime' => 'uptime_24h', 'speed' => 'lab_score'];

    /**
     * @param  Period|int  $period  A Period, or the number of days that end today.
     * @return list<array<string, mixed>>
     */
    public function projects(Period|int $period = 30, ?int $projectId = null): array
    {
        $period = $period instanceof Period ? $period : Period::preset($period);
        $days = $period->days;
        $projects = Project::query()->with(['group', 'sources'])->when($projectId, fn ($q) => $q->whereKey($projectId))->when(! $projectId, fn ($q) => $q->where('archived', false))->orderBy('name')->get();
        $ids = $projects->flatMap(fn ($project) => $project->sources->pluck('id'))->all();
        $now = CarbonImmutable::now('UTC');
        $start = $period->earliestDate($now);
        $last = $period->latestDate($now);
        $daily = DailyMetric::query()->whereIn('source_id', $ids)->where('date', '>=', $start)->where('date', '<=', $last)->orderBy('date')->get()->groupBy('source_id');
        $periods = PeriodMetric::query()->whereIn('source_id', $ids)->where('start_date', '>=', $start)->get()->groupBy('source_id');
        $monitors = DailyMetric::query()->whereIn('source_id', $ids)->where('metric', 'monitor_status')->where('date', '>=', $now->subDays(2)->toDateString())->orderBy('date')->get()->groupBy('source_id');

        return $projects->map(function ($project) use ($period, $days, $now, $daily, $periods, $monitors) {
            $sources = $project->sources->map(function ($source) use ($period, $days, $now, $daily, $periods, $monitors) {
                [$start, $end] = $period->window($source->reporting_timezone, $now);
                [$previousStart, $previousEnd] = $period->previousWindow($source->reporting_timezone, $now);
                $rows = $daily->get($source->id, collect())->groupBy('metric');
                $periodRows = $periods->get($source->id, collect());
                $metrics = [];
                foreach (ProviderCatalog::metricKeys($source->kind) as $key) {
                    $definition = MetricRegistry::get($key);
                    $byDate = $rows->get($key, collect())->keyBy(fn (DailyMetric $row): string => $row->date->toDateString());
                    $series = [];
                    $previous = [];
                    for ($i = 0; $i < $days; $i++) {
                        foreach ([['start' => $start, 'previous' => false], ['start' => $previousStart, 'previous' => true]] as $window) {
                            $date = $window['start']->addDays($i)->toDateString();
                            $row = $byDate->get($date);
                            $point = ['date' => $date, 'value' => $row?->value, 'partial' => ($row === null ? false : (bool) $row->is_partial), 'context' => $row->context ?? []];
                            if ($window['previous']) {
                                $previous[] = $point;
                            } else {
                                $series[] = $point;
                            }
                        }
                    }
                    $value = $this->aggregate($definition->aggregation, $series, $periodRows, $key, $start->toDateString(), $end->toDateString());
                    $prior = $this->aggregate($definition->aggregation, $previous, $periodRows, $key, $previousStart->toDateString(), $previousEnd->toDateString());
                    $known = count(array_filter($series, fn ($point) => $point['value'] !== null));
                    $periodRow = $periodRows->first(fn ($row) => $row->metric === $key && $row->start_date->toDateString() === $start->toDateString() && $row->end_date->toDateString() === $end->toDateString());
                    $partial = $definition->aggregation === 'period' ? ($periodRow ? (bool) $periodRow->is_partial : ($days === 1 && $value !== null ? (bool) $series[0]['partial'] : true)) : (! $definition->snapshot && ($known < $days || count(array_filter($series, fn ($p) => $p['partial'])) > 0));
                    $last = array_values(array_filter($series, fn ($point) => $point['value'] !== null));
                    $observedDate = $last ? end($last)['date'] : null;
                    if ($definition->unit === 'currency') {
                        $currencies = array_unique(array_column(array_column($last, 'context'), 'currency'));
                        $previousCurrencies = array_unique(array_column(array_column($previous, 'context'), 'currency'));
                        $currency = $last ? (end($last)['context']['currency'] ?? null) : null;
                        if ($definition->aggregation === 'sum' && count($currencies) > 1) {
                            $value = null;
                        }
                        if ($currency === null || $previousCurrencies !== [$currency] || count($currencies) > 1) {
                            $prior = null;
                            $previous = [];
                        }
                    }
                    // Snapshots compare their latest value with the last one of the previous period, as a difference.
                    $delta = $definition->snapshot && $definition->unit !== 'status' && $prior !== null && $value !== null ? $value - $prior : null;
                    $metrics[] = ['key' => $key, 'label' => __($definition->label), 'unit' => $definition->unit, 'value' => $value, 'display_value' => $this->format($key, $value), 'previous' => $prior, 'change' => ! $definition->snapshot && $prior !== null && $prior > 0 && $value !== null ? 100 * ($value - $prior) / $prior : null, 'delta' => $delta, 'partial' => $partial, 'series' => $series, 'previous_series' => $previous, 'coverage' => ['known' => $known, 'total' => $days], 'observed_date' => $observedDate, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'timezone' => $source->reporting_timezone, 'snapshot' => $definition->snapshot, 'context' => $observedDate ? ($byDate->get($observedDate)->context ?? []) : []];
                }
                // Health always looks at today, whatever period is shown.
                $today = $now->setTimezone($source->reporting_timezone)->startOfDay();
                $status = ! $source->enabled ? 'disabled' : ($source->consecutive_failures > 0 ? 'failed' : (! $source->last_success_at ? 'pending' : ($source->last_success_at->lt($now->subHours($source->kind === 'pagespeed' ? 36 : ($source->kind === 'uptimerobot' ? 2 : 8))) ? 'failed' : (($source->latest_data_date && $source->latest_data_date < $today->subDays(3)->toDateString()) ? 'delayed' : 'healthy'))));
                // The current monitor status, also when the period lies in the past.
                $monitor = $monitors->get($source->id)?->last();
                if ($source->enabled && in_array($monitor?->value, [8, 9, 8.0, 9.0], true)) {
                    $status = 'failed';
                }

                return ['id' => $source->id, 'label' => $source->label, 'kind' => $source->kind, 'reporting_timezone' => $source->reporting_timezone, 'enabled' => $source->enabled, 'status' => $status, 'last_success_at' => $source->last_success_at ? $source->last_success_at->utc()->translatedFormat('j M Y, H:i').' UTC' : null, 'last_success_timestamp' => $source->last_success_at?->getTimestamp(), 'latest_date' => $source->latest_data_date, 'error_code' => $source->error_code, 'metrics' => $metrics, 'page_speed' => $source->kind === 'pagespeed' ? $source->config : null];
            })->all();

            return ['id' => $project->id, 'name' => $project->name, 'slug' => $project->slug, 'domain' => $project->domain, 'group' => $project->group?->name, 'archived' => $project->archived, 'sources' => $sources, 'status' => $this->projectStatus($sources), 'summary' => $this->summary($sources, $days)];
        })->all();
    }

    /**
     * One headline value per kind of data, so the overview can show each project on a single row.
     * Additive metrics are summed across sources; visitors are never summed, so traffic uses visits.
     *
     * @param  list<array{label: string, kind: string, enabled: bool, status: string, metrics: list<array<string, mixed>>}>  $sources
     * @return array<string, array<string, mixed>>
     */
    private function summary(array $sources, int $days): array
    {
        $summary = [];
        foreach (self::CATEGORIES as $category => $key) {
            $members = array_values(array_filter($sources, fn (array $source): bool => $this->metric($source, $key) !== null));
            if (! $members) {
                continue;
            }
            $summary[$category] = match ($category) {
                'uptime' => $this->uptime($members), 'speed' => $this->speed($members, $days), default => $this->combine($members, $key, $days)
            };
            if ($category === 'development') {
                $summary[$category]['secondary'] = $this->combine($members, 'prs_merged', $days)['value'];
            }
        }

        return $summary;
    }

    /** @param list<array<string,mixed>> $sources
     * @return array<string,mixed>
     */
    private function speed(array $sources, int $days): array
    {
        $metrics = array_map(fn (array $source): array => $this->metric($source, 'lab_score'), $sources);
        $values = array_values(array_filter(array_column($metrics, 'value'), fn ($value) => $value !== null));
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $points = array_values(array_filter(array_map(fn (array $metric) => $metric['series'][$i]['value'] ?? null, $metrics), fn ($value) => $value !== null));
            $series[] = count($points) === count($metrics) ? min($points) : null;
        }

        return ['key' => 'lab_score', 'unit' => 'score', 'value' => $values ? min($values) : null, 'previous' => null, 'change' => null,
            'partial' => count($values) !== count($metrics), 'series' => $series,
            'parts' => array_map(fn (array $source): array => ['label' => $source['label'].' · '.__(($source['page_speed']['strategy'] ?? 'mobile') === 'desktop' ? 'Desktop' : 'Mobile'), 'value' => $this->metric($source, 'lab_score')['display_value']], $sources)];
    }

    /**
     * Sums one metric across sources. Daily points are aligned by position in each source's own reporting window.
     *
     * @param  list<array{label: string, kind: string, enabled: bool, status: string, metrics: list<array<string, mixed>>}>  $sources
     * @return array<string, mixed>
     */
    private function combine(array $sources, string $key, int $days): array
    {
        $metrics = [];
        $parts = [];
        foreach ($sources as $source) {
            $metric = $this->metric($source, $key);
            if ($metric === null) {
                continue;
            }
            $metrics[] = $metric;
            $parts[] = ['label' => $source['label'], 'value' => $metric['display_value']];
        }
        $reported = array_values(array_filter($metrics, fn (array $metric): bool => $metric['value'] !== null));
        $snapshot = MetricRegistry::get($key)->snapshot;
        $value = $reported ? (float) array_sum(array_column($reported, 'value')) : null;
        $previous = $reported && ! in_array(null, array_column($reported, 'previous'), true) ? (float) array_sum(array_column($reported, 'previous')) : null;
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = array_filter(array_map(fn (array $metric): ?float => $metric['series'][$i]['value'] ?? null, $metrics), fn (?float $point): bool => $point !== null);
            // A snapshot total needs an observation from every source on that date.
            // Otherwise it would suggest an artificial rise/fall as sources report.
            $series[] = $day && (! $snapshot || count($day) === count($metrics)) ? (float) array_sum($day) : null;
        }

        return ['key' => $key, 'unit' => MetricRegistry::get($key)->unit, 'value' => $value, 'previous' => $previous, 'change' => ! $snapshot && $value !== null && $previous !== null && $previous > 0 ? 100 * ($value - $previous) / $previous : null, 'partial' => in_array(true, array_column($metrics, 'partial'), true), 'series' => $series, 'parts' => $parts];
    }

    /**
     * @param  list<array{label: string, kind: string, enabled: bool, status: string, metrics: list<array<string, mixed>>}>  $sources
     * @return array<string, mixed>
     */
    private function uptime(array $sources): array
    {
        $active = array_values(array_filter($sources, fn (array $source): bool => (bool) $source['enabled']));
        $statuses = array_map(fn (array $source): ?int => ($status = $this->metric($source, 'monitor_status')['value'] ?? null) === null ? null : (int) $status, $active);
        $uptimes = array_values(array_filter(array_map(fn (array $source): ?float => $this->metric($source, 'uptime_24h')['value'] ?? null, $active), fn (?float $value): bool => $value !== null));
        $up = count(array_filter($statuses, fn (?int $status): bool => $status === 2));
        $down = count(array_filter($statuses, fn (?int $status): bool => in_array($status, [8, 9], true)));
        $parts = array_map(fn (array $source): array => ['label' => $source['label'], 'value' => $this->metric($source, 'monitor_status')['display_value'] ?? '—'], $sources);
        // The lowest daily uptime among the monitors, on dates when every one of them reported.
        $metrics = array_values(array_filter(array_map(fn (array $source): ?array => $this->metric($source, 'uptime_24h'), $active)));
        $series = [];
        foreach ($metrics ? array_keys($metrics[0]['series']) : [] as $i) {
            $points = array_values(array_filter(array_map(fn (array $metric) => $metric['series'][$i]['value'] ?? null, $metrics), fn ($value) => $value !== null));
            $series[] = $points && count($points) === count($metrics) ? min($points) : null;
        }

        return ['key' => 'uptime_24h', 'unit' => 'percent', 'value' => $uptimes ? min($uptimes) : null, 'up' => $up, 'down' => $down, 'total' => count($active), 'state' => $down ? 'down' : ($active && $up === count($active) ? 'up' : 'unknown'), 'parts' => $parts, 'series' => $series];
    }

    /** @param list<array{label: string, kind: string, enabled: bool, status: string, metrics: list<array<string, mixed>>}> $sources */
    private function projectStatus(array $sources): string
    {
        $statuses = array_column($sources, 'status');
        foreach (['failed', 'delayed', 'pending', 'healthy'] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return $statuses ? 'disabled' : 'empty';
    }

    /**
     * @param  array{metrics: list<array<string, mixed>>}  $source
     * @return array<string, mixed>|null
     */
    private function metric(array $source, string $key): ?array
    {
        return collect($source['metrics'])->firstWhere('key', $key);
    }

    /** @param list<array{date: string, value: float|null, partial: bool}> $series
     * @param  Collection<int, PeriodMetric>  $periods
     */
    private function aggregate(string $aggregation, array $series, Collection $periods, string $metric, string $start, string $end): ?float
    {
        if ($aggregation === 'period') {
            $row = $periods->first(fn ($row) => $row->metric === $metric && $row->start_date->toDateString() === $start && $row->end_date->toDateString() === $end);

            // A single day's daily value is already that day's unique count.
            return $row !== null ? $row->value : ($start === $end ? ($series[0]['value'] ?? null) : null);
        }
        $values = array_values(array_filter(array_column($series, 'value'), fn ($value) => $value !== null));
        if (! $values) {
            return null;
        }

        return $aggregation === 'latest' ? (float) end($values) : (float) array_sum($values);
    }

    private function format(string $metric, ?float $value): string
    {
        if ($value === null) {
            return '—';
        }if ($metric === 'monitor_status') {
            return [0 => __('Paused'), 1 => __('Not checked yet'), 2 => __('Up'), 8 => __('Seems down'), 9 => __('Down')][(int) $value] ?? __('Unknown');
        }

        return match (MetricRegistry::get($metric)->unit) {
            'ratio' => Number::format($value, 3, locale: app()->getLocale()), 'score' => Number::format($value, 0, locale: app()->getLocale()).'/100', 'percent' => Number::format($value, 2, locale: app()->getLocale()).'%', 'ms' => Number::format($value, 1, locale: app()->getLocale()).' ms',default => Number::format($value, 0, locale: app()->getLocale())
        };
    }
}
