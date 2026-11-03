<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Metribay\Core\Connectors\BingConnector;
use Metribay\Core\Metrics\Period;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\SearchQuery;
use Metribay\Core\Models\SearchQueryMonth;
use Metribay\Core\Models\SearchQueryMonthRow;

/**
 * The project page's Top queries block: per Search Console or Bing data source, the stored queries of one period or
 * archived month as three lists (clicked, ranked and opportunities) compared with the period or month before.
 * See docs/PROJECT_PAGE.md.
 */
class SearchQueries
{
    /** Hidden-chart key of the block; it is not a metric. */
    public const CHART_KEY = 'top_queries';

    /** Rows shown before "Show all". */
    public const TOP = 10;

    /** Opportunities: average positions that round to 4–20 ... */
    public const OPPORTUNITY_POSITIONS = [3.5, 20.5];

    /** ... with at least this many impressions ... */
    public const OPPORTUNITY_IMPRESSIONS = 10;

    /** Below this share of the site's clicks, the block says that the provider left queries out. */
    private const COVERAGE_NOTE_BELOW = 0.95;

    /**
     * Reports for the reporting period, or for one archived calendar month compared with another.
     *
     * Stored windows are the 7, 30 and 90 days up to the latest reported date. Yesterday and custom ranges use the
     * closest of them ("fallback"); the project page opens a custom range that is exactly an archived month on that month.
     *
     * @param  list<array<string, mixed>>  $sources  Project sources from DashboardData.
     * @param  string|null  $month  Archived month (Y-m) to show instead of the period.
     * @param  string|null  $compare  Month (Y-m) to compare an archived month with, "none", or null for the month before.
     * @return array<int, array<string, mixed>> Reports keyed by source ID; in the period view, sources without stored queries are left out.
     */
    public function forSources(array $sources, Period|int $period, ?string $month = null, ?string $compare = null): array
    {
        $period = $period instanceof Period ? $period : Period::preset($period);
        $sources = array_values(array_filter($sources, fn (array $source): bool => in_array($source['kind'], ProviderCatalog::QUERY_PROVIDERS, true)));
        if ($sources === []) {
            return [];
        }
        $ids = array_map(fn (array $source): int => (int) $source['id'], $sources);
        if ($month !== null) {
            return $this->forMonth($sources, $month, $compare);
        }
        $days = self::storedDays($period);
        $stored = SearchQuery::query()->whereIn('source_id', $ids)->where('days', $days)->get()->groupBy('source_id');
        $reports = [];
        foreach ($sources as $source) {
            $rows = $stored->get($source['id']);
            $current = $rows?->where('previous', false);
            if ($current === null || $current->isEmpty()) {
                continue;
            }
            $first = $current->first();
            $reports[(int) $source['id']] = ['days' => $days, 'fallback' => ! $period->isPreset(), 'month' => null, 'compare' => null, 'missing' => false, 'weeks' => $source['kind'] === 'bing' ? BingConnector::QUERY_WEEKS[$days] : null]
                + $this->report($source, $current->all(), $rows->where('previous', true)->keyBy('query')->all(), $first->start_date->toDateString(), $first->end_date->toDateString(), $current->contains('is_partial', true));
        }

        return $reports;
    }

    /** The stored window (7, 30 or 90 days up to the latest date) closest in length to a period. */
    public static function storedDays(Period $period): int
    {
        $closest = 90;
        foreach (array_reverse(Period::PRESETS) as $days) {
            if (abs($period->days - $days) <= abs($period->days - $closest)) {
                $closest = $days;
            }
        }

        return $closest;
    }

    /**
     * Archived months (Y-m) of the given sources, newest first.
     *
     * @param  list<int>  $sourceIds
     * @return list<string>
     */
    public function months(array $sourceIds): array
    {
        return SearchQueryMonth::query()->whereIn('source_id', $sourceIds)->orderByDesc('month')->pluck('month')
            ->map(fn (CarbonImmutable $month): string => $month->format('Y-m'))->unique()->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     * @return array<int, array<string, mixed>>
     */
    private function forMonth(array $sources, string $month, ?string $compare): array
    {
        $ids = array_map(fn (array $source): int => (int) $source['id'], $sources);
        $compareMonth = $compare === 'none' ? null : ($compare ?? CarbonImmutable::parse($month.'-01')->subMonthNoOverflow()->format('Y-m'));
        $archives = SearchQueryMonth::query()->with('rows')->whereIn('source_id', $ids)
            ->where(fn ($query) => $query->whereDate('month', $month.'-01')->when($compareMonth, fn ($query) => $query->orWhereDate('month', $compareMonth.'-01')))
            ->get()->groupBy('source_id');
        $reports = [];
        foreach ($sources as $source) {
            $byMonth = $archives->get($source['id'], collect())->keyBy(fn (SearchQueryMonth $archive): string => $archive->month->format('Y-m'));
            $current = $byMonth->get($month);
            $before = $compareMonth ? $byMonth->get($compareMonth) : null;
            $start = CarbonImmutable::parse($month.'-01');
            $base = ['days' => null, 'fallback' => false, 'month' => $month, 'compare' => $before ? $compareMonth : null, 'missing' => $current === null, 'weeks' => $current?->weeks];
            $reports[(int) $source['id']] = $base + $this->report($source, $current ? $current->rows->all() : [], $before ? $before->rows->keyBy('query')->all() : [],
                $current?->start_date->toDateString() ?? $start->toDateString(), $current?->end_date->toDateString() ?? $start->endOfMonth()->toDateString(), false);
        }

        return $reports;
    }

    /**
     * Clicked, ranked and opportunity lists of one set of queries, each compared with the same query in $previous.
     *
     * @param  array<string, mixed>  $source
     * @param  array<int, SearchQuery|SearchQueryMonthRow>  $current
     * @param  array<string, SearchQuery|SearchQueryMonthRow>  $previous  Keyed by query.
     * @return array<string, mixed>
     */
    private function report(array $source, array $current, array $previous, string $start, string $end, bool $partial): array
    {
        $list = [];
        foreach ($current as $row) {
            $before = $previous[$row->query] ?? null;
            $list[] = ['query' => $row->query, 'clicks' => $row->clicks, 'impressions' => $row->impressions,
                'ctr' => $row->impressions > 0 ? $row->clicks / $row->impressions : null, 'position' => $row->position,
                'change' => $before && $before->clicks > 0 ? 100 * ($row->clicks - $before->clicks) / $before->clicks : null,
                // Positive when the query moved up: position 8 after 11 is an improvement of 3.
                'position_change' => $before && $before->position !== null && $row->position !== null ? $before->position - $row->position : null,
                'new' => $before === null && $previous !== []];
        }
        $clicks = array_sum(array_column($list, 'clicks'));
        $impressions = array_sum(array_column($list, 'impressions'));
        $site = $source['kind'] === 'gsc' && $list !== [] ? $this->siteTotals((int) $source['id'], $start, $end) : null;
        $ctr = $site && $site['impressions'] > 0 ? $site['clicks'] / $site['impressions'] : ($impressions > 0 ? $clicks / $impressions : null);

        return [
            'source' => $source, 'start_date' => $start, 'end_date' => $end,
            'weekly' => $source['kind'] === 'bing', 'partial' => $partial, 'count' => count($list), 'site_ctr' => $ctr,
            'coverage' => $site && $site['clicks'] > 0 && $clicks / $site['clicks'] < self::COVERAGE_NOTE_BELOW ? min(1, $clicks / $site['clicks']) : null,
            'clicked' => $this->sorted(array_filter($list, fn (array $row): bool => $row['clicks'] > 0), ['clicks', 'impressions']),
            'ranked' => $this->sorted($list, ['impressions', 'clicks']),
            'opportunities' => $this->sorted(array_filter($list, fn (array $row): bool => self::isOpportunity($row, $ctr)), ['impressions', 'clicks']),
        ];
    }

    /**
     * A query that ranks just below the top results, is seen often and is clicked less than the site's average.
     *
     * @param  array{impressions: float, position: float|null, ctr: float|null}  $row
     */
    public static function isOpportunity(array $row, ?float $siteCtr): bool
    {
        [$from, $to] = self::OPPORTUNITY_POSITIONS;

        return $siteCtr !== null && $row['position'] !== null && $row['position'] >= $from && $row['position'] < $to
            && $row['impressions'] >= self::OPPORTUNITY_IMPRESSIONS && ($row['ctr'] ?? 0.0) < $siteCtr;
    }

    /** @return array{clicks: float, impressions: float}|null Site totals from the daily charts, when every date of the window is recorded. */
    private function siteTotals(int $sourceId, string $start, string $end): ?array
    {
        $rows = DailyMetric::query()->where('source_id', $sourceId)->whereIn('metric', ['clicks', 'impressions'])
            ->where('date', '>=', $start)->where('date', '<=', $end)->get(['metric', 'date', 'value']);
        $days = (int) round((strtotime($end) - strtotime($start)) / 86400) + 1;
        $totals = [];
        foreach (['clicks', 'impressions'] as $metric) {
            $values = $rows->where('metric', $metric);
            if ($values->count() < $days) {
                return null;
            }
            $totals[$metric] = (float) $values->sum('value');
        }

        return $totals;
    }

    /**
     * Largest first by the given keys; equal rows keep alphabetical order.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function sorted(array $rows, array $keys): array
    {
        usort($rows, function (array $a, array $b) use ($keys): int {
            foreach ($keys as $key) {
                if ($a[$key] !== $b[$key]) {
                    return $b[$key] <=> $a[$key];
                }
            }

            return strcmp($a['query'], $b['query']);
        });

        return $rows;
    }
}
