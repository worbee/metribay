<?php

namespace Metribay\Core\Metrics;

use InvalidArgumentException;

final class MetricRegistry
{
    /** @return array<string, MetricDefinition> */
    public static function all(): array
    {
        $definitions = [
            ['visitors', 'Visitors', 'count', 'period'], ['visits', 'Visits', 'count', 'sum'], ['pageviews', 'Page views', 'count', 'sum'],
            // Stored parts of the engagement ratios: bounced visits and the total length of visits in seconds.
            ['bounces', 'Bounces', 'count', 'sum'], ['visit_duration', 'Total visit duration', 'seconds', 'sum'],
            ['bounce_rate', 'Bounce rate', 'percent', 'ratio', false, ['bounces', 'visits', 100.0]],
            ['avg_visit_duration', 'Average visit duration', 'seconds', 'ratio', false, ['visit_duration', 'visits', 1.0]],
            ['conversions', 'Conversions', 'count', 'sum'],
            ['impressions', 'Impressions', 'count', 'sum'], ['clicks', 'Clicks', 'count', 'sum'], ['downloads', 'Downloads', 'count', 'sum'],
            ['release_downloads', 'Release downloads', 'count', 'latest', true],
            ['followers', 'Followers', 'count', 'latest', true],
            ['active_users', 'Active users', 'count', 'period'], ['key_events', 'Key events', 'count', 'sum'],
            ['published_items', 'Items published', 'count', 'sum'], ['published_urls', 'Published URLs', 'count', 'latest', true],
            ['new_urls', 'New URLs detected', 'count', 'sum'],
            ['indexed_pages', 'Indexed pages', 'count', 'latest'], ['commits', 'Commits', 'count', 'sum'], ['prs_merged', 'PRs merged', 'count', 'sum'],
            ['loc_changed', 'Lines changed', 'lines', 'sum'],
            ['pipeline_runs_succeeded', 'Pipeline runs succeeded', 'count', 'sum'], ['pipeline_runs_failed', 'Pipeline runs failed', 'count', 'sum'], ['open_issues', 'Open issues', 'count', 'latest', true],
            ['paddle_revenue', 'Paddle net revenue', 'currency', 'sum'], ['paddle_mrr', 'Monthly recurring revenue', 'currency', 'latest', true],
            ['paddle_active_subscribers', 'Active paying subscribers', 'count', 'latest', true],
            ['monitor_status', 'Monitor status', 'status', 'latest', true], ['uptime_24h', 'Uptime · last 24h', 'percent', 'latest', true],
            ['response_time_24h', 'Response time · last 24h', 'ms', 'latest', true],
        ];
        foreach ([['lab_score', 'Lab performance score', 'score'], ['lab_lcp', 'Lab LCP', 'ms'], ['lab_cls', 'Lab CLS', 'ratio'], ['lab_tbt', 'Lab total blocking time', 'ms'], ['crux_lcp', 'Real users · page LCP', 'ms'], ['crux_inp', 'Real users · page INP', 'ms'], ['crux_cls', 'Real users · page CLS', 'ratio'], ['crux_origin_lcp', 'Real users · site LCP', 'ms'], ['crux_origin_inp', 'Real users · site INP', 'ms'], ['crux_origin_cls', 'Real users · site CLS', 'ratio']] as [$key, $label, $unit]) {
            $definitions[] = [$key, $label, $unit, 'latest', true];
        }
        $all = [];
        foreach ($definitions as $definition) {
            $metric = new MetricDefinition(...$definition);
            $all[$metric->key] = $metric;
        }

        return $all;
    }

    public static function get(string $key): MetricDefinition
    {
        return self::all()[$key] ?? throw new InvalidArgumentException('Unknown metric.');
    }

    /** Whether the metric is computed from other metrics instead of being stored. */
    public static function derived(string $key): bool
    {
        return self::get($key)->ratio !== null;
    }
}
