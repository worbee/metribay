<?php

namespace Metribay\Core\Tests\Feature;

use Metribay\Core\Metrics\ProjectLayout;
use Tests\TestCase;

class ProjectLayoutTest extends TestCase
{
    /** @return array<string, mixed> */
    private function metric(string $key, bool $data = true): array
    {
        return ['key' => $key, 'label' => $key, 'unit' => 'count', 'value' => $data ? 1.0 : null, 'series' => [['date' => '2026-11-01', 'value' => $data ? 1.0 : null, 'partial' => false]], 'previous_series' => []];
    }

    /** @param list<string> $keys */
    private function fake(int $id, string $kind, array $keys, array $without = [], ?string $synced = '1 Nov 2026, 10:00 UTC'): array
    {
        return ['id' => $id, 'label' => $kind.' '.$id, 'kind' => $kind, 'status' => $synced ? 'healthy' : 'pending', 'enabled' => true, 'last_success_at' => $synced, 'metrics' => array_map(fn (string $key) => $this->metric($key, ! in_array($key, $without, true)), $keys)];
    }

    public function test_groups_follow_overview_order_with_one_lead_per_group(): void
    {
        $rows = ProjectLayout::rows([
            $this->fake(1, 'uptimerobot', ['monitor_status', 'uptime_24h', 'response_time_24h']),
            $this->fake(2, 'github', ['commits', 'prs_merged', 'loc_changed', 'open_issues']),
            $this->fake(3, 'umami', ['visitors', 'visits', 'pageviews']),
            $this->fake(4, 'wporg', ['downloads']),
        ]);
        $groups = array_merge(...$rows);
        $this->assertSame(['audience', 'development', 'distribution', 'reliability'], array_column($groups, 'key'));

        // Visits leads although the provider lists visitors first; the two smalls stack beside it.
        $audience = $groups[0]['cards'];
        $this->assertSame(['visits', 'visitors', 'pageviews'], array_map(fn ($card) => $card['metric']['key'], $audience));
        $this->assertSame([['l', 4, 2], ['s', 2, 1], ['s', 2, 1]], array_map(fn ($card) => [$card['size'], $card['span'], $card['rows']], $audience));

        // Open issues is a number tile; alone in its row it spans the group as a slim row.
        $issues = collect($groups[1]['cards'])->firstWhere('metric.key', 'open_issues');
        $this->assertSame(['n', 6, true], [$issues['size'], $issues['span'], $issues['solo']]);

        // Distribution (one chart) and Reliability (status strip and two smalls) share a row.
        $this->assertSame(['distribution', 'reliability'], array_column($rows[2], 'key'));
        $this->assertTrue($rows[2][0]['half']);
        $this->assertSame([['strip', 6], ['s', 3], ['s', 3]], array_map(fn ($card) => [$card['size'], $card['span']], $rows[2][1]['cards']));
    }

    public function test_missing_lead_promotes_the_next_metric_and_empty_charts_collapse(): void
    {
        [$group] = ProjectLayout::rows([$this->fake(1, 'umami', ['visitors', 'visits', 'pageviews'], ['visits'])])[0];
        $this->assertSame(['visitors', 'l', 4], [$group['cards'][0]['metric']['key'], $group['cards'][0]['size'], $group['cards'][0]['span']]);
        $this->assertSame(['visits'], array_map(fn ($line) => $line['metric']['key'], $group['lines']));
    }

    public function test_several_sources_get_labelled_charts_and_pending_sources_add_none(): void
    {
        [$group] = ProjectLayout::rows([
            $this->fake(1, 'github', ['commits', 'prs_merged', 'loc_changed', 'open_issues']),
            $this->fake(2, 'github', ['commits', 'prs_merged', 'loc_changed', 'open_issues']),
            $this->fake(3, 'azure_devops', ['commits', 'prs_merged', 'pipeline_runs_succeeded', 'pipeline_runs_failed', 'open_issues'], [], null),
        ])[0];
        $this->assertCount(3, $group['sources']);
        $this->assertSame([1, 2], array_values(array_unique(array_map(fn ($card) => $card['source']['id'], $group['cards']))));
        $this->assertSame(['github 1', 'github 2'], array_slice(array_map(fn ($card) => $card['label'], $group['cards']), 0, 2));
        $this->assertSame([], $group['lines']);
    }

    public function test_polarity_quality_and_units(): void
    {
        $this->assertSame('up', ProjectLayout::polarity('visits'));
        $this->assertSame('down', ProjectLayout::polarity('pipeline_runs_failed'));
        $this->assertSame('down', ProjectLayout::polarity('crux_origin_inp'));
        $this->assertSame('neutral', ProjectLayout::polarity('loc_changed'));
        $this->assertSame(['good', 'needs-improvement', 'poor'], [ProjectLayout::quality('lab_score', 90), ProjectLayout::quality('lab_score', 50), ProjectLayout::quality('lab_score', 49)]);
        $this->assertSame(['good', 'needs-improvement', 'poor'], [ProjectLayout::quality('crux_lcp', 2500), ProjectLayout::quality('lab_lcp', 4000), ProjectLayout::quality('crux_origin_lcp', 4001)]);
        $this->assertSame(['good', 'poor'], [ProjectLayout::quality('lab_cls', 0.1), ProjectLayout::quality('crux_cls', 0.26)]);
        $this->assertNull(ProjectLayout::quality('visits', 5));
        $this->assertNull(ProjectLayout::quality('lab_lcp', null));
        $this->assertSame(['2.9', 's'], ProjectLayout::format(2900, 'ms'));
        $this->assertSame(['186', 'ms'], ProjectLayout::format(186, 'ms'));
        $this->assertSame(['99.86', '%'], ProjectLayout::format(99.86, 'percent'));
        $this->assertSame(['86', '/100'], ProjectLayout::format(86, 'score'));
    }
}
