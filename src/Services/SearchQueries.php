<?php

namespace Metribay\Core\Services;

use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\SearchQuery;

/**
 * The project page's Top queries block: per Search Console or Bing data source, the stored queries of one period
 * as three lists (clicked, ranked and opportunities) compared with the period before. See docs/PROJECT_PAGE.md.
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
     * @param  list<array<string, mixed>>  $sources  Project sources from DashboardData.
     * @return array<int, array<string, mixed>> Reports keyed by source ID; sources without stored queries are left out.
     */
    public function forSources(array $sources, int $days): array
    {
        $sources = array_values(array_filter($sources, fn (array $source): bool => in_array($source['kind'], ProviderCatalog::QUERY_PROVIDERS, true)));
        if ($sources === []) {
            return [];
        }
        $stored = SearchQuery::query()->whereIn('source_id', array_column($sources, 'id'))->where('days', $days)->get()->groupBy('source_id');
        $reports = [];
        foreach ($sources as $source) {
            $rows = $stored->get($source['id']);
            $current = $rows?->where('previous', false);
            if ($current === null || $current->isEmpty()) {
                continue;
            }
            $previous = $rows->where('previous', true)->keyBy('query');
            $first = $current->first();
            $list = [];
            foreach ($current as $row) {
                $before = $previous->get($row->query);
                $list[] = ['query' => $row->query, 'clicks' => $row->clicks, 'impressions' => $row->impressions,
                    'ctr' => $row->impressions > 0 ? $row->clicks / $row->impressions : null, 'position' => $row->position,
                    'change' => $before && $before->clicks > 0 ? 100 * ($row->clicks - $before->clicks) / $before->clicks : null,
                    // Positive when the query moved up: position 8 after 11 is an improvement of 3.
                    'position_change' => $before && $before->position !== null && $row->position !== null ? $before->position - $row->position : null,
                    'new' => $before === null && $previous->isNotEmpty()];
            }
            $clicks = array_sum(array_column($list, 'clicks'));
            $impressions = array_sum(array_column($list, 'impressions'));
            $site = $source['kind'] === 'gsc' ? $this->siteTotals((int) $source['id'], $first->start_date->toDateString(), $first->end_date->toDateString()) : null;
            $ctr = $site && $site['impressions'] > 0 ? $site['clicks'] / $site['impressions'] : ($impressions > 0 ? $clicks / $impressions : null);
            $reports[(int) $source['id']] = [
                'source' => $source, 'start_date' => $first->start_date->toDateString(), 'end_date' => $first->end_date->toDateString(),
                'weekly' => $source['kind'] === 'bing', 'partial' => $current->contains('is_partial', true), 'count' => count($list), 'site_ctr' => $ctr,
                'coverage' => $site && $site['clicks'] > 0 && $clicks / $site['clicks'] < self::COVERAGE_NOTE_BELOW ? min(1, $clicks / $site['clicks']) : null,
                'clicked' => $this->sorted(array_filter($list, fn (array $row): bool => $row['clicks'] > 0), ['clicks', 'impressions']),
                'ranked' => $this->sorted($list, ['impressions', 'clicks']),
                'opportunities' => $this->sorted(array_filter($list, fn (array $row): bool => self::isOpportunity($row, $ctr)), ['impressions', 'clicks']),
            ];
        }

        return $reports;
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
