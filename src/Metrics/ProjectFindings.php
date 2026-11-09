<?php

namespace Metribay\Core\Metrics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Number;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\SearchQuery;
use Metribay\Core\Services\SearchQueries;

/**
 * Up to three recent findings for a project, from data already stored in the workspace.
 * A current monitor incident comes first, then the largest qualifying fall in Google search clicks.
 * Thresholds are the first pilot values. See cloud/docs/PROJECT_OVERVIEW_PLAN.md.
 */
class ProjectFindings
{
    public const MIN_CLICK_LOSS = 25;

    public const MIN_CLICK_RATIO = 0.20;

    private const WINDOW = 7;

    private const MAX = 3;

    /** @var list<string> */
    private const DEPLOY_ORIGINS = ['github_release', 'github_deployment', 'azure_devops_deployment'];

    /**
     * @param  list<array<string, mixed>>  $sources  Project sources from DashboardData, including current_monitor and hidden_keys.
     * @return array{findings: list<array<string, mixed>>, checked: bool, notes: list<string>}
     */
    public function forSources(array $sources, int $projectId, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? CarbonImmutable::now('UTC'))->utc();
        $notes = [];
        $checked = false;
        $incidents = [];
        foreach ($sources as $source) {
            if (($source['kind'] ?? '') !== 'uptimerobot') {
                continue;
            }
            $monitor = $source['current_monitor'] ?? null;
            if (! is_array($monitor)) {
                continue;
            }
            if ($monitor['withheld'] === null) {
                $checked = true;
            }
            if (! empty($monitor['incident'])) {
                $incidents[] = $this->incident($source, $monitor);
            } elseif ($note = $this->monitorNote($source, $monitor)) {
                $notes[] = $note;
            }
        }

        $losses = [];
        foreach ($sources as $source) {
            if (($source['kind'] ?? '') !== 'gsc') {
                continue;
            }
            $result = $this->search($source, $projectId, $now);
            $checked = $checked || $result['checked'];
            if ($result['note'] !== null) {
                $notes[] = $result['note'];
            }
            if ($result['finding'] !== null) {
                $losses[] = $result['finding'];
            }
        }
        usort($losses, fn (array $a, array $b): int => [$b['loss'], $a['source_id']] <=> [$a['loss'], $b['source_id']]);

        $hasMonitor = array_filter($sources, fn (array $source): bool => ($source['kind'] ?? '') === 'uptimerobot') !== [];
        $hasSearch = array_filter($sources, fn (array $source): bool => ($source['kind'] ?? '') === 'gsc') !== [];
        if ($sources !== [] && ! $hasMonitor && ! $hasSearch) {
            $notes[] = __('These checks look for a current UptimeRobot incident and a fall in Google Search Console clicks. Connect one of those sources to see them.');
        }

        $findings = array_values(array_filter([$incidents ? $this->monitorFinding($incidents) : null, $losses[0] ?? null]));

        return ['findings' => array_slice($findings, 0, self::MAX), 'checked' => $checked, 'notes' => $notes];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array{value: int, date: string, checked_at: ?string, incident: bool, withheld: ?string}  $monitor
     * @return array<string, mixed>
     */
    private function incident(array $source, array $monitor): array
    {
        $hidden = $source['hidden_keys'] ?? [];

        return [
            'source_id' => (int) $source['id'],
            'label' => (string) $source['label'],
            'value' => (int) $monitor['value'],
            'checked' => $this->checked($monitor['checked_at'] ?? null, (string) $monitor['date']),
            'anchor' => in_array('monitor_status', $hidden, true) ? 'source-attention-'.$source['id'] : 'chart-'.$source['id'].'-monitor_status',
        ];
    }

    /**
     * @param  list<array{source_id: int, label: string, value: int, checked: string, anchor: string}>  $incidents
     * @return array<string, mixed>
     */
    private function monitorFinding(array $incidents): array
    {
        $titles = [];
        $evidence = [];
        foreach ($incidents as $incident) {
            $status = $this->statusLabel($incident['value']);
            $titles[] = __(':label: :status.', ['label' => $incident['label'], 'status' => $status]);
            $checked = __(':provider · checked :time', ['provider' => 'UptimeRobot', 'time' => $incident['checked']]);
            $evidence[] = count($incidents) > 1 ? $incident['label'].' · '.$checked : $checked;
        }

        return [
            'kind' => 'monitor_incident', 'source_id' => $incidents[0]['source_id'], 'loss' => 0.0,
            'title' => implode(' ', $titles), 'evidence' => implode(' ', $evidence), 'extra' => null, 'context' => null,
            'action' => __('Open Reliability'), 'anchor' => count($incidents) === 1 ? $incidents[0]['anchor'] : 'group-reliability', 'period' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array{value: int, date: string, checked_at: ?string, withheld: ?string}  $monitor
     */
    private function monitorNote(array $source, array $monitor): ?string
    {
        if (! in_array((int) $monitor['value'], [8, 9], true)) {
            return null;
        }
        $replace = ['label' => (string) $source['label'], 'status' => $this->statusLabel((int) $monitor['value']), 'time' => $this->checked($monitor['checked_at'] ?? null, (string) $monitor['date'])];

        return match ($monitor['withheld'] ?? null) {
            'disabled' => __('Last known result for :label: :status, checked :time. The monitor is paused, so this is not a current incident.', $replace),
            'failed' => __('Last known result for :label: :status, checked :time. The latest check failed, so this is not a current incident.', $replace),
            'stale' => __('Last known result for :label: :status, checked :time. The data is stale, so this is not a current incident.', $replace),
            default => __('Last known result for :label: :status. The monitor has not reported a current check, so this is not a current incident.', $replace),
        };
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{checked: bool, note: ?string, finding: ?array<string, mixed>}
     */
    private function search(array $source, int $projectId, CarbonImmutable $now): array
    {
        $property = (string) $source['label'];
        if (($source['status'] ?? '') !== 'healthy' || ! ($source['enabled'] ?? false)) {
            $note = match ($source['status'] ?? '') {
                'disabled' => __('Google Search Console for :property is paused, so clicks were not compared.', ['property' => $property]),
                'pending' => __('Google Search Console for :property is still importing, so clicks were not compared.', ['property' => $property]),
                'delayed' => __('Google Search Console for :property is delayed, so clicks were not compared.', ['property' => $property]),
                default => __('Google Search Console for :property needs attention, so clicks were not compared.', ['property' => $property]),
            };

            return ['checked' => false, 'note' => $note, 'finding' => null];
        }

        $today = $now->setTimezone((string) $source['reporting_timezone'])->startOfDay();
        $rows = DailyMetric::query()->where('source_id', $source['id'])->where('metric', 'clicks')
            ->where('date', '>=', $today->subDays(21)->toDateString())->where('date', '<=', $today->toDateString())->orderBy('date')->get();
        $end = $rows->filter(fn (DailyMetric $row): bool => ! $row->is_partial)->last();
        if ($end === null) {
            return ['checked' => false, 'note' => __('Google Search Console for :property has no final daily click total in the last 7 days.', ['property' => $property]), 'finding' => null];
        }
        $endDate = $end->date->toDateString();
        if ($endDate < $today->subDays(self::WINDOW - 1)->toDateString()) {
            return ['checked' => false, 'note' => __('The latest final click day for :property is :end, which is outside the last 7 days, so the change was not checked.', ['property' => $property, 'end' => $this->day($endDate)]), 'finding' => null];
        }

        $endDay = CarbonImmutable::parse($endDate);
        $currentStart = $endDay->subDays(self::WINDOW - 1);
        $previousEnd = $currentStart->subDay();
        $previousStart = $previousEnd->subDays(self::WINDOW - 1);
        $byDate = $rows->keyBy(fn (DailyMetric $row): string => $row->date->toDateString());
        $current = $this->sum($byDate, $currentStart);
        $previous = $this->sum($byDate, $previousStart);
        if ($current === null || $previous === null) {
            return ['checked' => false, 'note' => __('Google Search Console for :property is missing final daily click totals for the 14 days ending :end.', ['property' => $property, 'end' => $this->day($endDate)]), 'finding' => null];
        }
        if ($previous <= 0) {
            return ['checked' => false, 'note' => __('Google Search Console for :property was not compared because the previous 7 days (:range) had no clicks.', ['property' => $property, 'range' => $this->range($previousStart->toDateString(), $previousEnd->toDateString())]), 'finding' => null];
        }
        $loss = $previous - $current;
        if (! $this->qualifies($previous, $current)) {
            return ['checked' => true, 'note' => null, 'finding' => null];
        }

        $named = $this->namedLoss((int) $source['id'], $currentStart->toDateString(), $endDate, $previousStart->toDateString(), $previousEnd->toDateString());
        $hidden = $source['hidden_keys'] ?? [];
        $range = $this->range($currentStart->toDateString(), $endDate);
        $opensQueries = $named !== null && ! in_array(SearchQueries::CHART_KEY, $hidden, true);

        return ['checked' => true, 'note' => null, 'finding' => [
            'kind' => 'search_clicks', 'source_id' => (int) $source['id'], 'loss' => $loss,
            'title' => __('Search clicks fell on :property', ['property' => $property]),
            'evidence' => __(':provider recorded :current clicks from :start to :end, down from :previous in the previous 7 days (:previous_range).', [
                'provider' => 'Google Search Console', 'current' => $this->count($current), 'previous' => $this->count($previous),
                'start' => $this->day($currentStart->toDateString()), 'end' => $this->day($endDate),
                'previous_range' => $this->range($previousStart->toDateString(), $previousEnd->toDateString()),
            ]),
            'extra' => $named ? __('“:query” fell from :previous to :current clicks over those same dates.', ['query' => $named['query'], 'previous' => $this->count($named['previous']), 'current' => $this->count($named['current'])]) : null,
            'context' => $this->context($projectId, $currentStart->toDateString(), $endDate),
            'action' => $opensQueries ? __('Open Top queries, :range', ['range' => $range]) : __('Open Search, :range', ['range' => $range]),
            'anchor' => $opensQueries ? 'queries-search' : (in_array('clicks', $hidden, true) ? 'group-search' : 'chart-'.$source['id'].'-clicks'),
            'period' => ['from' => $currentStart->toDateString(), 'to' => $endDate],
        ]];
    }

    /** @param \Illuminate\Support\Collection<string, DailyMetric> $rows */
    private function sum($rows, CarbonImmutable $start): ?float
    {
        $total = 0.0;
        for ($i = 0; $i < self::WINDOW; $i++) {
            $row = $rows->get($start->addDays($i)->toDateString());
            if ($row === null || $row->is_partial) {
                return null;
            }
            $total += (float) $row->value;
        }

        return $total;
    }

    private function qualifies(float $previous, float $current): bool
    {
        if ($previous <= 0) {
            return false;
        }
        $loss = $previous - $current;

        return $loss >= self::MIN_CLICK_LOSS && ($loss / $previous) + 1e-9 >= self::MIN_CLICK_RATIO;
    }

    /** @return array{query: string, previous: float, current: float}|null */
    private function namedLoss(int $sourceId, string $currentStart, string $currentEnd, string $previousStart, string $previousEnd): ?array
    {
        $rows = SearchQuery::query()->where('source_id', $sourceId)->where('days', self::WINDOW)->where('is_partial', false)
            ->where(function ($query) use ($currentStart, $currentEnd, $previousStart, $previousEnd): void {
                $query->where(fn ($query) => $query->where('previous', false)->whereDate('start_date', $currentStart)->whereDate('end_date', $currentEnd))
                    ->orWhere(fn ($query) => $query->where('previous', true)->whereDate('start_date', $previousStart)->whereDate('end_date', $previousEnd));
            })->get();
        $best = null;
        foreach ($rows->where('previous', false)->groupBy('query') as $text => $currentRows) {
            if ($currentRows->count() !== 1) {
                continue;
            }
            $previousRows = $rows->where('previous', true)->where('query', $text);
            if ($previousRows->count() !== 1) {
                continue;
            }
            $current = (float) $currentRows->first()->clicks;
            $previous = (float) $previousRows->first()->clicks;
            if (! $this->qualifies($previous, $current)) {
                continue;
            }
            $loss = $previous - $current;
            if ($best === null || $loss > $best['loss'] || ($loss === $best['loss'] && strcmp((string) $text, $best['query']) < 0)) {
                $best = ['query' => (string) $text, 'previous' => $previous, 'current' => $current, 'loss' => $loss];
            }
        }

        return $best === null ? null : ['query' => $best['query'], 'previous' => $best['previous'], 'current' => $best['current']];
    }

    private function context(int $projectId, string $from, string $to): ?string
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        $hits = [];
        foreach (ProjectEvent::query()->where('project_id', $projectId)->whereIn('origin', self::DEPLOY_ORIGINS)->orderBy('date')->get() as $event) {
            foreach ($event->occurrencesBetween($start, $end) as $date) {
                $hits[] = $event->title.' ('.$this->day($date).')';
            }
        }
        if ($hits === []) {
            return null;
        }

        return __('Also recorded in this window: :items.', ['items' => implode(', ', array_slice($hits, 0, 2))]);
    }

    private function statusLabel(int $value): string
    {
        return [8 => __('Seems down'), 9 => __('Down')][$value] ?? __('Unknown');
    }

    private function checked(?string $iso, string $date): string
    {
        return $iso ? CarbonImmutable::parse($iso)->utc()->translatedFormat('j M Y, H:i').' UTC' : $this->day($date);
    }

    private function day(string $date): string
    {
        return CarbonImmutable::parse($date)->translatedFormat('j M Y');
    }

    private function range(string $from, string $to): string
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        if ($from === $to) {
            return $end->translatedFormat('j M Y');
        }

        return $start->translatedFormat($start->year === $end->year ? 'j M' : 'j M Y').' – '.$end->translatedFormat('j M Y');
    }

    private function count(float $value): string
    {
        return Number::format($value, 0, locale: app()->getLocale());
    }
}
