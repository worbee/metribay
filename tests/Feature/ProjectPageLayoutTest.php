<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\HiddenChart;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\SearchQueryMonth;
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
    private function source(string $kind, string $label, array $values, array $state = [], array $context = []): Source
    {
        return $this->scoped(function () use ($kind, $label, $values, $state, $context) {
            $now = CarbonImmutable::now('UTC');
            $source = Source::create(['project_id' => $this->project->id, 'kind' => $kind, 'label' => $label, 'config' => [], 'identity_hash' => hash('sha256', $kind.$label), 'reporting_timezone' => 'UTC', 'last_success_at' => $now, 'latest_data_date' => $now->toDateString()]);
            $source->forceFill($state)->save();
            $rows = [];
            foreach ($values as $metric => $value) {
                for ($i = 0; $i < 10; $i++) {
                    $row = ['metric' => $metric, 'date' => $now->subDays($i)->toDateString(), 'value' => $value, 'is_partial' => false];
                    if ($context !== []) {
                        $row['context'] = $context;
                    }
                    $rows[] = $row;
                }
            }
            app(MetricStore::class)->persist($source, new CollectionResult($rows), $now);

            return $source;
        });
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

    public function test_overview_and_all_metrics_share_the_period_and_archive(): void
    {
        $this->source('umami', 'Website', ['visits' => 4.0]);
        $search = $this->source('gsc', 'Search', ['clicks' => 2.0]);
        $this->scoped(fn () => SearchQueryMonth::create([
            'source_id' => $search->id, 'month' => '2026-10-01', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'query_count' => 1, 'collected_at' => now(),
        ]));
        $url = '/w/layout/projects/'.$this->project->id;
        $params = ['workspace' => 'layout', 'project' => $this->project->id];

        $this->actingAs($this->owner)->get($url.'?days=7')->assertOk()
            ->assertSee('aria-current="page" >All metrics', false)
            ->assertSee('data-group-toggle', false)
            ->assertSee('aria-expanded="true"', false)
            ->assertSee('aria-controls="group-audience-body"', false)
            ->assertSee('Expand all')
            ->assertSee('id="group-audience-heading"', false)
            ->assertDontSee('Recent findings');

        $thirty = route('metribay.projects.show', $params + ['days' => 30, 'queries' => '2026-10', 'view' => 'overview']);
        $metrics = route('metribay.projects.show', $params + ['days' => 7, 'queries' => '2026-10']);
        $this->get($url.'?view=overview&days=7&queries=2026-10')->assertOk()
            ->assertSee('aria-current="page" >Overview', false)
            ->assertSee('Recent findings')
            ->assertSee('Google Search Console for Search is missing final daily click totals')
            ->assertSee('href="'.e($thirty).'"', false)
            ->assertSee('href="'.e($metrics).'"', false)
            ->assertDontSee('data-metric-chart', false)
            ->assertDontSee('data-group-toggle', false)
            ->assertDontSee('No significant changes in the data we checked.');

        $this->get($url.'?view=metrics&days=7')->assertOk()->assertSee('aria-current="page" >All metrics', false)->assertSee('id="group-search-heading"', false);
        $this->get($url.'?view=nope')->assertOk()->assertSee('aria-current="page" >All metrics', false)->assertSee('id="group-audience-heading"', false);
    }

    public function test_a_group_without_a_summary_link_gets_its_own_navigation(): void
    {
        $this->source('umami', 'Website', ['visits' => 4.0]);
        $this->actingAs($this->owner)->get('/w/layout/projects/'.$this->project->id)->assertOk()->assertDontSee('class="group-nav"', false);

        $this->source('paddle', 'Billing', ['paddle_revenue' => 12.0], context: ['currency' => 'EUR']);
        $this->get('/w/layout/projects/'.$this->project->id)->assertOk()
            ->assertSee('class="group-nav"', false)
            ->assertSee('href="#group-revenue">Revenue', false)
            ->assertSee('href="#group-audience"', false);
    }

    public function test_presets_and_custom_mixes_keep_the_period_and_a_way_back_to_every_chart(): void
    {
        $this->source('umami', 'Website', ['visits' => 4.0]);
        $failed = $this->source('gsc', 'Search', ['clicks' => 2.0], ['consecutive_failures' => 1, 'error_code' => 'ProviderRequestLimit']);
        $this->source('azure_devops', 'Pipelines', ['commits' => 3.0]);
        $this->source('paddle', 'Billing', ['paddle_revenue' => 12.0], context: ['currency' => 'EUR']);
        $url = '/w/layout/projects/'.$this->project->id;
        $params = ['workspace' => 'layout', 'project' => $this->project->id];
        $metrics = route('metribay.projects.show', $params + ['days' => 7]);
        $searchThirty = route('metribay.projects.show', $params + ['days' => 30, 'view' => 'search']);

        $this->actingAs($this->owner)->get($url.'?view=search&days=7')->assertOk()
            ->assertSee('aria-current="page" >Search', false)
            ->assertSee('Development and Issues')
            ->assertSee('Custom view')
            ->assertSee('name="groups[]" value="search"', false)
            ->assertSee('name="view" value="search"', false)
            ->assertSee('href="'.e($searchThirty).'"', false)
            ->assertSee('href="'.e($metrics).'"', false)
            ->assertSee('id="group-search-heading"', false)
            ->assertSee('id="source-attention-'.$failed->id.'"', false)
            ->assertDontSee('id="group-audience-heading"', false)
            ->assertDontSee('id="group-development-heading"', false)
            ->assertDontSee('id="group-revenue-heading"', false)
            ->assertSee('href="'.e($metrics).'#group-audience"', false)
            ->assertSee('href="#group-search"', false);

        $this->get($url.'?view=development&days=7')->assertOk()
            ->assertSee('aria-current="page" >Development and Issues', false)
            ->assertSee('id="group-development-heading"', false)
            ->assertDontSee('id="group-search-heading"', false)
            ->assertSee('id="source-attention-'.$failed->id.'"', false)
            ->assertSee('href="#source-attention-'.$failed->id.'"', false);

        $this->get($url.'?view=revenue&days=7')->assertOk()
            ->assertSee('aria-current="page" >Revenue', false)
            ->assertSee('id="group-revenue-heading"', false)
            ->assertDontSee('id="group-audience-heading"', false);

        $this->get($url.'?view=search&groups=audience&days=7')->assertOk()
            ->assertSee('aria-current="page" >Search', false)
            ->assertDontSee('id="group-audience-heading"', false);

        $this->get($url.'?groups=audience,search&days=7')->assertOk()
            ->assertSee('aria-current="page" >Custom view', false)
            ->assertSee('id="group-audience-heading"', false)
            ->assertSee('id="group-search-heading"', false)
            ->assertDontSee('id="group-development-heading"', false)
            ->assertSee('href="'.e($metrics).'#group-development"', false)
            ->assertSee('href="#group-audience"', false);

        $this->get($url.'?groups[]=development&groups[]=revenue&days=7')->assertOk()
            ->assertSee('id="group-development-heading"', false)
            ->assertSee('id="group-revenue-heading"', false)
            ->assertDontSee('id="group-audience-heading"', false);

        $this->get($url.'?groups=nope&days=7')->assertOk()
            ->assertSee('aria-current="page" >All metrics', false)
            ->assertSee('id="group-audience-heading"', false);

        $this->get($url.'?view=overview&days=7')->assertOk()
            ->assertSee('aria-current="page" >Overview', false)
            ->assertSee('Development and Issues')
            ->assertDontSee('data-group-toggle', false);

        $this->owner->forceFill(['locale' => 'de'])->save();
        $this->get($url.'?view=development&days=7')->assertOk()->assertSee('Entwicklung und Issues')->assertSee('Eigene Ansicht');
    }

    public function test_a_view_offers_only_groups_the_project_has(): void
    {
        $this->source('umami', 'Website', ['visits' => 4.0]);
        $url = '/w/layout/projects/'.$this->project->id;

        $this->actingAs($this->owner)->get($url)->assertOk()
            ->assertSee('Custom view')
            ->assertSee('name="groups[]" value="audience"', false)
            ->assertDontSee('Development and Issues')
            ->assertDontSee('>Search</a>', false)
            ->assertDontSee('>Revenue</a>', false);

        $this->get($url.'?view=revenue')->assertOk()
            ->assertSee('aria-current="page" >Revenue', false)
            ->assertSee('This project has no charts in this view.')
            ->assertDontSee('id="group-audience-heading"', false);
    }
}
