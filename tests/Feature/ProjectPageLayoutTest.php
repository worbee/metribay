<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Metrics\ProjectLayout;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\HiddenChart;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\MetricStore;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ProjectPageLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Layout', 'slug' => 'layout']);
        $this->project = $this->scoped(fn () => Project::create(['name' => 'Layout', 'slug' => 'layout', 'domain' => 'layout.example.test']));
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback);
    }

    /** @param array<string, float> $values metric => daily value for the last ten days */
    private function source(string $kind, string $label, array $values, array $state = []): Source
    {
        return $this->scoped(function () use ($kind, $label, $values, $state) {
            $now = CarbonImmutable::now('UTC');
            $source = Source::create(['project_id' => $this->project->id, 'kind' => $kind, 'label' => $label, 'config' => [], 'identity_hash' => hash('sha256', $kind.$label), 'reporting_timezone' => 'UTC', 'last_success_at' => $now, 'latest_data_date' => $now->toDateString()]);
            $source->forceFill($state)->save();
            $rows = [];
            foreach ($values as $metric => $value) {
                for ($i = 0; $i < 10; $i++) {
                    $rows[] = ['metric' => $metric, 'date' => $now->subDays($i)->toDateString(), 'value' => $value, 'is_partial' => false];
                }
            }
            app(MetricStore::class)->persist($source, new CollectionResult($rows), $now);

            return $source;
        });
    }

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

    public function test_single_attention_link_targets_the_down_monitor(): void
    {
        $this->freezeTime();
        $healthy = $this->source('uptimerobot', 'Web', ['monitor_status' => 2.0]);
        $down = $this->source('uptimerobot', 'Workers', ['monitor_status' => 9.0]);

        $this->actingAs($this->owner)->get('/w/layout/projects/'.$this->project->id)->assertOk()
            ->assertSee('href="#chart-'.$down->id.'-monitor_status">1 needs attention</a>', false)
            ->assertSee('id="chart-'.$down->id.'-monitor_status"', false)
            ->assertDontSee('href="#chart-'.$healthy->id.'-monitor_status"', false);
    }

    public function test_multiple_attention_sources_each_link_to_their_own_block(): void
    {
        $this->freezeTime();
        $down = $this->source('uptimerobot', 'Workers', ['monitor_status' => 8.0]);
        $failed = $this->source('gsc', 'Search account', ['clicks' => 1.0], ['consecutive_failures' => 1, 'error_code' => 'ProviderRequestLimit']);
        $delayed = $this->source('bing', 'Delayed search', ['clicks' => 1.0]);
        $this->scoped(fn () => $delayed->forceFill(['latest_data_date' => CarbonImmutable::now('UTC')->subDays(5)->toDateString()])->save());

        $this->actingAs($this->owner)->get('/w/layout/projects/'.$this->project->id)->assertOk()
            ->assertSee('2 need attention')
            ->assertSee('href="#chart-'.$down->id.'-monitor_status">Workers</a>', false)
            ->assertSee('href="#source-attention-'.$failed->id.'">Search account</a>', false)
            ->assertSee('id="source-attention-'.$failed->id.'"', false)
            ->assertSee('href="#source-attention-'.$delayed->id.'">1 delayed</a>', false)
            ->assertSee('id="source-attention-'.$delayed->id.'"', false);
    }

    public function test_expired_google_access_points_to_reconnecting_the_account(): void
    {
        $this->freezeTime();
        $connection = $this->scoped(function () {
            $connection = new Connection(['workspace_id' => $this->workspace->id, 'kind' => 'gsc', 'name' => 'GSC · fixture']);
            $connection->storeSecrets(['client_id' => 'fixture-id', 'client_secret' => 'fixture-secret', 'refresh_token' => 'fixture-refresh']);
            $connection->save();

            return $connection;
        });
        $failed = $this->source('gsc', 'Search account', ['clicks' => 1.0], ['connection_id' => $connection->id, 'consecutive_failures' => 1, 'error_code' => 'GoogleAuthorizationExpired', 'last_error_at' => CarbonImmutable::now('UTC')]);

        $this->actingAs($this->owner)->get('/w/layout/projects/'.$this->project->id)->assertOk()
            ->assertSee('Google access for this data source’s connected account expired or was revoked. Reconnect the account to resume collection.')
            ->assertSee('href="'.route('metribay.settings', ['workspace' => 'layout', 'tab' => 'accounts']).'#connection-'.$connection->id.'">Reconnect account', false)
            ->assertDontSee('Reference: GoogleAuthorizationExpired');
        $this->get('/w/layout/projects/'.$this->project->id.'/settings')->assertOk()
            ->assertSee('Google access expired or was revoked; reconnect the account');
        $this->assertNotNull($failed->id);
    }

    public function test_hidden_charts_leave_an_attention_warning_target(): void
    {
        $this->freezeTime();
        $down = $this->source('uptimerobot', 'Workers', ['monitor_status' => 9.0]);
        $this->scoped(function () use ($down) {
            foreach (['monitor_status', 'uptime_24h', 'response_time_24h'] as $key) {
                HiddenChart::create(['project_id' => $this->project->id, 'source_id' => $down->id, 'metric_key' => $key]);
            }
        });

        $this->actingAs($this->owner)->get('/w/layout/projects/'.$this->project->id)->assertOk()
            ->assertSee('href="#source-attention-'.$down->id.'">1 needs attention</a>', false)
            ->assertSee('id="source-attention-'.$down->id.'"', false)
            ->assertDontSee('id="chart-'.$down->id.'-monitor_status"', false);
    }

    public function test_page_shows_compact_header_summary_groups_and_coloured_changes(): void
    {
        $this->source('umami', 'Website', ['visits' => 12.0, 'visitors' => 9.0, 'pageviews' => 30.0]);
        $this->source('azure_devops', 'Pipelines', ['commits' => 3.0, 'pipeline_runs_failed' => 2.0]);
        $this->source('uptimerobot', 'Production', ['monitor_status' => 2.0, 'uptime_24h' => 100.0]);
        $this->source('gsc', 'Search', ['clicks' => 1.0], ['consecutive_failures' => 1]);
        $this->scoped(function () {
            // Failures rose from 1 to 2 a day: a rise that is bad.
            $source = Source::query()->where('kind', 'azure_devops')->firstOrFail();
            $now = CarbonImmutable::now('UTC');
            $rows = [];
            for ($i = 7; $i < 14; $i++) {
                $rows[] = ['metric' => 'pipeline_runs_failed', 'date' => $now->subDays($i)->toDateString(), 'value' => 1.0, 'is_partial' => false];
            }
            app(MetricStore::class)->persist($source, new CollectionResult($rows), $now);
        });

        $html = $this->actingAs($this->owner)->get('/w/layout/projects/'.$this->project->id.'?days=7')->assertOk()
            ->assertSee('4 data sources · ')->assertSee('1 needs attention')->assertSee('Review in settings')
            ->assertSee('class="project-summary"', false)->assertSee('href="#group-audience"', false)
            ->assertSeeInOrder(['id="group-audience-heading"', 'id="group-search-heading"', 'id="group-development-heading"', 'id="group-reliability-heading"'], false)
            ->assertSee('Google Search Console</strong> · ', false)->assertSee('class="status-strip"', false)->assertSee('Up on 7 of 7 recorded days')
            ->getContent();
        $header = substr($html, strpos($html, 'class="project-head"'), strpos($html, '</header>', strpos($html, 'class="project-head"')) - strpos($html, 'class="project-head"'));
        $this->assertStringNotContainsString('button-primary', $header);
        $this->assertMatchesRegularExpression('/days=7"\s+aria-current="page"\s*>7 days</', $html);
        preg_match('/id="chart-\d+-pipeline_runs_failed-label".*?<\/section>/s', $html, $failures);
        $this->assertStringContainsString('cell-change is-bad', $failures[0]);
        preg_match('/id="chart-\d+-visits-label".*?<\/section>/s', $html, $visits);
        $this->assertStringContainsString('in the previous 7 days', $visits[0]);

        $this->owner->forceFill(['locale' => 'de'])->save();
        $this->get('/w/layout/projects/'.$this->project->id)->assertOk()->assertSee('Publikum')->assertSee('Zuverlässigkeit')->assertSee('Tageswerte');
    }
}
