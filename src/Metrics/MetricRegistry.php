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
            ['impressions', 'Impressions', 'count', 'sum'], ['clicks', 'Clicks', 'count', 'sum'], ['downloads', 'Downloads', 'count', 'sum'],
            ['indexed_pages', 'Indexed pages', 'count', 'latest'], ['commits', 'Commits', 'count', 'sum'], ['prs_merged', 'PRs merged', 'count', 'sum'],
            ['loc_changed', 'Lines changed', 'lines', 'sum'], ['open_issues', 'Open issues', 'count', 'latest', true],
            ['monitor_status', 'Monitor status', 'status', 'latest', true], ['uptime_24h', 'Uptime · last 24h', 'percent', 'latest', true],
            ['response_time_24h', 'Response time · last 24h', 'ms', 'latest', true],
        ];
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
}
