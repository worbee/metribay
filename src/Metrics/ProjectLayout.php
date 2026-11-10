<?php

namespace Metribay\Core\Metrics;

use Illuminate\Support\Number;
use Metribay\Core\Services\ProviderCatalog;
use Metribay\Core\Services\SearchQueries;

/**
 * Default layout of a project page: metrics grouped by meaning (in overview column order), one lead chart per group,
 * smaller charts beside it and number tiles for snapshots. See docs/PROJECT_PAGE.md.
 */
final class ProjectLayout
{
    /** Groups in overview column order; metrics lead first. */
    public const GROUPS = [
        'revenue' => ['paddle_revenue', 'paddle_mrr', 'paddle_active_subscribers'],
        'audience' => ['visits', 'visitors', 'pageviews'],
        'search' => ['clicks', 'impressions', 'indexed_pages'],
        'development' => ['commits', 'prs_merged', 'loc_changed', 'pipeline_runs_succeeded', 'pipeline_runs_failed', 'open_issues'],
        'distribution' => ['downloads', 'release_downloads'],
        'content' => ['published_items', 'published_urls'],
        'reliability' => ['monitor_status', 'uptime_24h', 'response_time_24h'],
        'page_speed' => ['lab_score', 'lab_lcp', 'lab_cls', 'lab_tbt', 'crux_lcp', 'crux_inp', 'crux_cls', 'crux_origin_lcp', 'crux_origin_inp', 'crux_origin_cls'],
    ];

    public const TITLES = ['revenue' => 'Revenue', 'audience' => 'Audience', 'search' => 'Search', 'development' => 'Development', 'distribution' => 'Distribution', 'content' => 'Content', 'reliability' => 'Reliability', 'page_speed' => 'Page speed'];

    /** Overview columns and the group each one summarises. */
    public const SUMMARY = ['traffic' => 'audience', 'search' => 'search', 'development' => 'development', 'issues' => 'development', 'downloads' => 'distribution', 'release_downloads' => 'distribution', 'content' => 'content', 'published_urls' => 'content', 'uptime' => 'reliability', 'speed' => 'page_speed'];

    /** A rise of these is bad. */
    public const LOWER_IS_BETTER = ['open_issues', 'pipeline_runs_failed', 'response_time_24h', 'lab_lcp', 'lab_cls', 'lab_tbt', 'crux_lcp', 'crux_inp', 'crux_cls', 'crux_origin_lcp', 'crux_origin_inp', 'crux_origin_cls'];

    /** Changes of these are never coloured. */
    public const NEUTRAL = ['loc_changed'];

    /** Snapshot counts shown as number tiles rather than charts. */
    public const NUMBER_TILES = ['open_issues'];

    /** Published page speed thresholds: [good up to, needs improvement up to]; the score counts upwards. */
    public const THRESHOLDS = ['score' => [90, 50], 'lcp' => [2500, 4000], 'inp' => [200, 500], 'cls' => [0.1, 0.25], 'tbt' => [200, 600]];

    /** Short page speed labels; the full registry label stays the accessible name. */
    public const SHORT_LABELS = ['lab_score' => 'Performance', 'lab_lcp' => 'LCP', 'lab_cls' => 'CLS', 'lab_tbt' => 'Total blocking time', 'crux_lcp' => 'LCP', 'crux_inp' => 'INP', 'crux_cls' => 'CLS', 'crux_origin_lcp' => 'LCP', 'crux_origin_inp' => 'INP', 'crux_origin_cls' => 'CLS'];

    /**
     * Rows of groups; a row holds one full-width group or two half-width ones.
     *
     * @param  list<array<string, mixed>>  $sources  Project sources from DashboardData, hidden charts already removed.
     * @param  bool  $singleDay  One day (yesterday) shows number tiles instead of charts.
     * @param  list<string>|null  $only  Group keys to keep. Null shows every group the project has.
     * @return list<list<array<string, mixed>>>
     */
    public static function rows(array $sources, bool $singleDay = false, ?array $only = null): array
    {
        $groups = [];
        $onlyKeys = $only === null ? null : array_flip($only);
        foreach (self::GROUPS as $key => $keys) {
            if ($onlyKeys !== null && ! isset($onlyKeys[$key])) {
                continue;
            }
            $members = array_values(array_filter($sources, fn (array $source): bool => array_intersect(ProviderCatalog::metricKeys($source['kind']), $keys) !== []));
            if ($members === []) {
                continue;
            }
            $cards = [];
            $lines = [];
            $multiple = count($members) > 1;
            foreach ($keys as $metricKey) {
                foreach ($members as $source) {
                    if (! $source['last_success_at']) {
                        continue;
                    }
                    $metric = array_values(array_filter($source['metrics'], fn (array $candidate): bool => $candidate['key'] === $metricKey))[0] ?? null;
                    if ($metric === null) {
                        continue;
                    }
                    $card = ['metric' => $metric, 'source' => $source, 'label' => $multiple ? $source['label'] : null];
                    if (array_filter(array_column($metric['series'], 'value'), fn (mixed $value): bool => $value !== null) !== []) {
                        $cards[] = $card;
                    } else {
                        $lines[] = $card;
                    }
                }
            }
            $hidden = array_sum(array_map(fn (array $source): int => count(array_intersect($source['hidden_keys'] ?? [], [...$keys, SearchQueries::CHART_KEY])), $members));
            $queries = array_values(array_filter(array_map(fn (array $source): ?array => $source['queries'] ?? null, $members)));
            // Search Console first: it reports daily and usually has the larger audience.
            usort($queries, fn (array $a, array $b): int => array_search($a['source']['kind'], ProviderCatalog::QUERY_PROVIDERS, true) <=> array_search($b['source']['kind'], ProviderCatalog::QUERY_PROVIDERS, true));
            // A group whose charts are all hidden leaves the page; the hidden-charts line at the end still counts them.
            if ($cards === [] && $lines === [] && $queries === [] && $hidden > 0 && ! collect($members)->contains(fn (array $source): bool => ! $source['last_success_at'] || in_array($source['status'], ['failed', 'delayed'], true))) {
                continue;
            }
            $groups[] = ['key' => $key, 'title' => self::TITLES[$key], 'sources' => $members, 'cards' => $cards, 'lines' => $lines, 'queries' => $queries, 'events' => self::events($members)];
        }

        $rows = [];
        for ($i = 0; $i < count($groups); $i++) {
            $group = $groups[$i];
            $next = $groups[$i + 1] ?? null;
            if ($group['key'] !== 'page_speed' && $next && $next['key'] !== 'page_speed' && self::fitsHalf($group) && self::fitsHalf($next)) {
                $rows[] = [self::place($group, true, $singleDay), self::place($next, true, $singleDay)];
                $i++;
            } else {
                $rows[] = [self::place($group, false, $singleDay)];
            }
        }

        return $rows;
    }

    /** "up" (a rise is good), "down" (a rise is bad) or "neutral". */
    public static function polarity(string $key): string
    {
        return in_array($key, self::NEUTRAL, true) ? 'neutral' : (in_array($key, self::LOWER_IS_BETTER, true) ? 'down' : 'up');
    }

    /** Page speed quality: "good", "needs-improvement", "poor", or null for other metrics and unknown values. */
    public static function quality(string $key, mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }
        $kind = $key === 'lab_score' ? 'score' : (preg_match('/_(lcp|inp|cls|tbt)$/', $key, $match) ? $match[1] : null);
        if ($kind === null) {
            return null;
        }
        [$good, $poor] = self::THRESHOLDS[$kind];
        $value = (float) $value;
        if ($kind === 'score') {
            return $value >= $good ? 'good' : ($value >= $poor ? 'needs-improvement' : 'poor');
        }

        return $value <= $good ? 'good' : ($value <= $poor ? 'needs-improvement' : 'poor');
    }

    /**
     * A value split into its number and unit, so the unit can be set smaller.
     *
     * @return array{0: string, 1: string|null}
     */
    public static function format(float $value, string $unit): array
    {
        $locale = app()->getLocale();

        return match ($unit) {
            'percent' => [Number::format($value, $value >= 100 ? 0 : 2, locale: $locale), '%'],
            'ms' => $value >= 1000 ? [Number::format($value / 1000, 1, locale: $locale), 's'] : [Number::format($value, 0, locale: $locale), 'ms'],
            'score' => [Number::format($value, 0, locale: $locale), '/100'],
            'ratio' => [Number::format($value, $value > 0 && $value < 0.01 ? 3 : 2, locale: $locale), null],
            default => [Number::format($value, fmod($value, 1) !== 0.0 ? 1 : 0, locale: $locale), null],
        };
    }

    /** Name of a chart that can be hidden: a metric, or the Top queries block. */
    public static function chartLabel(string $key): string
    {
        return $key === SearchQueries::CHART_KEY ? __('Top queries') : __(MetricRegistry::all()[$key]->label ?? $key);
    }

    /** @param array{cards: list<array<string, mixed>>, queries: list<array<string, mixed>>} $group */
    private static function fitsHalf(array $group): bool
    {
        $lead = $group['cards'][0]['metric']['key'] ?? null;

        // The Top queries table needs the full width.
        return $group['queries'] === [] && (count($group['cards']) === 1 || ($lead === 'monitor_status' && count($group['cards']) <= 3));
    }

    /**
     * Gives every card a size and its column span on a six-column grid.
     *
     * @param  array{key: string, title: string, sources: list<array<string, mixed>>, cards: list<array<string, mixed>>, lines: list<array<string, mixed>>, queries: list<array<string, mixed>>, events: int}  $group
     * @return array<string, mixed>
     */
    private static function place(array $group, bool $half, bool $singleDay = false): array
    {
        $group['half'] = $half;
        if ($group['key'] === 'page_speed') {
            return $group;
        }
        $cards = $group['cards'];
        $leadIndex = $singleDay ? false : (array_key_first(array_filter($cards, fn (array $card): bool => ! in_array($card['metric']['key'], self::NUMBER_TILES, true))) ?? false);
        if ($leadIndex !== false && $leadIndex > 0) {
            array_unshift($cards, ...array_splice($cards, $leadIndex, 1));
        }
        foreach ($cards as $index => &$card) {
            $key = $card['metric']['key'];
            $card['size'] = $key === 'monitor_status' ? 'strip' : ($singleDay || in_array($key, self::NUMBER_TILES, true) ? 'n' : ($index === 0 ? 'l' : 's'));
            $card['span'] = 2;
            $card['rows'] = 1;
            $card['solo'] = false;
        }
        unset($card);

        $rest = $cards;
        $lead = $leadIndex === false ? null : array_shift($rest);
        if ($lead !== null) {
            $beside = ($half || $lead['size'] === 'strip') ? 0 : min(2, count($rest));
            $lead['span'] = $beside > 0 ? 4 : 6;
            $lead['rows'] = max(1, $beside);
            $placed = [$lead];
            for ($i = 0; $i < $beside; $i++) {
                $placed[] = array_shift($rest);
            }
        } else {
            $placed = [];
        }
        $perRow = $half ? 2 : 3;
        foreach (array_chunk($rest, $perRow) as $row) {
            $count = count($row);
            foreach ($row as $card) {
                $card['span'] = $count === 1 ? 6 : intdiv(6, $count);
                $card['solo'] = $count === 1 && $card['size'] === 'n';
                $placed[] = $card;
            }
        }
        $group['cards'] = $placed;

        return $group;
    }

    /**
     * Distinct event markers across a group's sources, for its legend.
     *
     * @param  list<array<string, mixed>>  $sources
     */
    private static function events(array $sources): int
    {
        $markers = [];
        foreach ($sources as $source) {
            foreach ($source['events'] ?? [] as $event) {
                $markers[$event['date'].'|'.$event['title']] = true;
            }
        }

        return count($markers);
    }
}
