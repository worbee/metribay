<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Metribay\Core\Metrics\MetricRegistry;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\PeriodMetric;
use Metribay\Core\Models\Project;

class DashboardData
{
    /** @return list<array<string, mixed>> */
    public function projects(int $days = 30, ?int $projectId = null): array
    {
        $projects = Project::query()->with(['group', 'sources'])->when($projectId, fn ($q) => $q->whereKey($projectId))->when(! $projectId, fn ($q) => $q->where('archived', false))->orderBy('name')->get();
        $ids = $projects->flatMap(fn ($project) => $project->sources->pluck('id'))->all();
        $now = CarbonImmutable::now('UTC');
        $start = $now->subDays($days * 2 + 2)->toDateString();
        $daily = DailyMetric::query()->whereIn('source_id', $ids)->where('date', '>=', $start)->orderBy('date')->get()->groupBy('source_id');
        $periods = PeriodMetric::query()->whereIn('source_id', $ids)->where('start_date', '>=', $start)->get()->groupBy('source_id');

        return $projects->map(function ($project) use ($days, $now, $daily, $periods) {
            $sources = $project->sources->map(function ($source) use ($days, $now, $daily, $periods) {
                $end = $now->setTimezone($source->reporting_timezone)->startOfDay();
                $start = $end->subDays($days - 1);
                $previousEnd = $start->subDay();
                $previousStart = $previousEnd->subDays($days - 1);
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
                            $point = ['date' => $date, 'value' => $row?->value, 'partial' => ($row === null ? false : (bool) $row->is_partial)];
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
                    $period = $periodRows->first(fn ($row) => $row->metric === $key && $row->start_date->toDateString() === $start->toDateString() && $row->end_date->toDateString() === $end->toDateString());
                    $partial = $definition->aggregation === 'period' ? ($period ? (bool) $period->is_partial : true) : (! $definition->snapshot && ($known < $days || count(array_filter($series, fn ($p) => $p['partial'])) > 0));
                    $last = array_values(array_filter($series, fn ($point) => $point['value'] !== null));
                    $observedDate = $last ? end($last)['date'] : null;
                    $metrics[] = ['key' => $key, 'label' => __($definition->label), 'unit' => $definition->unit, 'value' => $value, 'display_value' => $this->format($key, $value), 'previous' => $prior, 'change' => ! $definition->snapshot && $prior !== null && $prior > 0 && $value !== null ? 100 * ($value - $prior) / $prior : null, 'partial' => $partial, 'series' => $series, 'previous_series' => $previous, 'coverage' => ['known' => $known, 'total' => $days], 'observed_date' => $observedDate, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'timezone' => $source->reporting_timezone];
                }
                $status = ! $source->enabled ? 'disabled' : ($source->consecutive_failures > 0 ? 'failed' : (! $source->last_success_at ? 'pending' : ($source->last_success_at->lt($now->subHours($source->kind === 'uptimerobot' ? 2 : 8)) ? 'failed' : (($source->latest_data_date && $source->latest_data_date < $end->subDays(3)->toDateString()) ? 'delayed' : 'healthy'))));
                $monitor = collect($metrics)->firstWhere('key', 'monitor_status');
                if ($source->enabled && in_array($monitor['value'] ?? null, [8, 9, 8.0, 9.0], true)) {
                    $status = 'failed';
                }

                return ['id' => $source->id, 'label' => $source->label, 'kind' => $source->kind, 'reporting_timezone' => $source->reporting_timezone, 'enabled' => $source->enabled, 'status' => $status, 'last_success_at' => $source->last_success_at ? $source->last_success_at->utc()->translatedFormat('j M Y, H:i').' UTC' : null, 'latest_date' => $source->latest_data_date, 'error_code' => $source->error_code, 'metrics' => $metrics];
            })->all();

            return ['id' => $project->id, 'name' => $project->name, 'slug' => $project->slug, 'domain' => $project->domain, 'group' => $project->group?->name, 'archived' => $project->archived, 'sources' => $sources];
        })->all();
    }

    /** @param list<array{date: string, value: float|null, partial: bool}> $series
     * @param  Collection<int, PeriodMetric>  $periods
     */
    private function aggregate(string $aggregation, array $series, Collection $periods, string $metric, string $start, string $end): ?float
    {
        if ($aggregation === 'period') {
            $row = $periods->first(fn ($row) => $row->metric === $metric && $row->start_date->toDateString() === $start && $row->end_date->toDateString() === $end);

            return $row?->value;
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
            'percent' => Number::format($value, 2, locale: app()->getLocale()).'%', 'ms' => Number::format($value, 1, locale: app()->getLocale()).' ms',default => Number::format($value, 0, locale: app()->getLocale())
        };
    }
}
