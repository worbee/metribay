<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\MetricStore;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

final class PageSpeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_edition_uses_own_key_and_preserves_url_and_device_until_confirmation(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Speed', 'slug' => 'speed']);
        $project = app(WorkspaceContext::class)->run($workspace, fn () => Project::create(['name' => 'Example', 'slug' => 'example']));
        $this->actingAs($owner)->get('/w/speed/sources/new?project='.$project->id)->assertOk()->assertSee('Page speed');
        $this->post('/w/speed/sources/setup', ['action' => 'provider', 'kind' => 'pagespeed'])->assertSessionHasNoErrors();
        $this->get('/w/speed/sources/new')->assertOk()->assertSee('Save account and continue');
        $this->post('/w/speed/sources/setup', ['action' => 'account', 'values' => ['api_key' => 'synthetic-private-key']])->assertSessionHasNoErrors();
        $this->get('/w/speed/sources/new')->assertOk()->assertSee('Page URL')->assertDontSee('synthetic-private-key');
        $this->post('/w/speed/sources/setup', ['action' => 'page_speed', 'url' => 'https://EXAMPLE.com', 'strategy' => 'desktop'])->assertSessionHasNoErrors();
        $this->get('/w/speed/sources/new')->assertOk()->assertSee('Desktop')->assertSee('Add and start tracking');
        $this->assertDatabaseCount('sources', 0);
        $this->post('/w/speed/sources/setup', ['action' => 'finish', 'days' => 180])->assertSessionHasNoErrors()->assertRedirect('/w/speed/projects/'.$project->id);
        app(WorkspaceContext::class)->run($workspace, function (): void {
            $source = Source::sole();
            $this->assertSame(['url' => 'https://example.com/', 'strategy' => 'desktop'], $source->config);
            $this->assertSame(Connection::sole()->id, $source->connection_id);
            $this->assertStringNotContainsString('synthetic-private-key', json_encode($source->toArray(), JSON_THROW_ON_ERROR));
        });
    }

    public function test_overview_uses_lowest_score_per_project_and_never_totals_scores_across_projects(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Scores', 'slug' => 'scores']);
        app(WorkspaceContext::class)->run($workspace, function (): void {
            foreach ([[90.0, 60.0], [80.0]] as $index => $values) {
                $project = Project::create(['name' => 'Example '.$index, 'slug' => 'example-'.$index]);
                foreach ($values as $position => $value) {
                    $source = Source::create(['project_id' => $project->id, 'kind' => 'pagespeed', 'label' => 'Page '.$position, 'reporting_timezone' => 'UTC', 'config' => ['url' => 'https://example.com/'.$position, 'strategy' => 'mobile'], 'identity_hash' => hash('sha256', $index.':'.$position), 'last_success_at' => now()]);
                    app(MetricStore::class)->persist($source, new CollectionResult([['metric' => 'lab_score', 'date' => now()->toDateString(), 'value' => $value, 'is_partial' => false]]), CarbonImmutable::now());
                }
            }
            $data = app(DashboardData::class)->projects();
            $this->assertSame(60.0, $data[0]['summary']['speed']['value']);
            $this->assertSame(80.0, $data[1]['summary']['speed']['value']);
        });
        $this->actingAs($owner)->get('/w/scores')->assertOk()->assertSee('Page speed · lowest')->assertSee('60/100')->assertSee('80/100')->assertDontSee('class="overview-totals"', false);
    }

    public function test_scheduled_speed_checks_run_daily_while_explicit_refreshes_remain_possible(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Daily speed', 'slug' => 'daily']);
        $source = app(WorkspaceContext::class)->run($workspace, function (): Source {
            $project = Project::create(['name' => 'Example', 'slug' => 'example']);

            return Source::create(['project_id' => $project->id, 'kind' => 'pagespeed', 'label' => 'Speed', 'reporting_timezone' => 'UTC', 'config' => ['url' => 'https://example.com/', 'strategy' => 'mobile'], 'identity_hash' => str_repeat('d', 64), 'last_success_at' => now()]);
        });
        $this->artisan('metribay:sync', ['--workspace' => 'daily'])->assertSuccessful();
        Queue::assertNothingPushed();
        $this->artisan('metribay:sync', ['--workspace' => 'daily', '--source' => $source->id])->assertSuccessful();
        Queue::assertPushed(CollectSource::class, 1);
        $this->travel(25)->hours();
        $this->artisan('metribay:sync', ['--workspace' => 'daily'])->assertSuccessful();
        Queue::assertPushed(CollectSource::class, 2);
    }

    public function test_speed_snapshots_are_latest_not_summed_with_precise_values_and_collection_context(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25T12:00:00Z'));
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Speed', 'slug' => 'speed']);
        $project = app(WorkspaceContext::class)->run($workspace, function (): Project {
            $project = Project::create(['name' => 'Example', 'slug' => 'example']);
            $source = Source::create(['project_id' => $project->id, 'kind' => 'pagespeed', 'label' => 'Speed', 'reporting_timezone' => 'UTC', 'config' => ['url' => 'https://example.com/', 'strategy' => 'mobile'], 'identity_hash' => str_repeat('c', 64), 'last_success_at' => now()]);
            $store = app(MetricStore::class);
            $store->persist($source, new CollectionResult([
                ['metric' => 'lab_score', 'date' => '2026-10-24', 'value' => 80.0, 'is_partial' => false],
                ['metric' => 'lab_score', 'date' => '2026-10-25', 'value' => 91.0, 'is_partial' => false, 'context' => ['type' => 'lab', 'field_status' => 'no_data']],
                ['metric' => 'lab_cls', 'date' => '2026-10-25', 'value' => 0.025, 'is_partial' => false],
                ['metric' => 'crux_origin_lcp', 'date' => '2026-10-23', 'value' => 1800.0, 'is_partial' => false, 'window_start' => '2026-09-26T00:00:00Z', 'window_end' => '2026-10-23T23:59:59Z', 'context' => ['type' => 'field', 'scope' => 'origin', 'device' => 'mobile', 'collection_start' => '2026-09-26', 'collection_end' => '2026-10-23']],
            ]), CarbonImmutable::now());
            $data = app(DashboardData::class)->projects(30, $project->id)[0];
            $this->assertSame(91.0, $data['summary']['speed']['value']);
            $this->assertNull($data['summary']['speed']['change']);
            $score = collect($data['sources'][0]['metrics'])->firstWhere('key', 'lab_score');
            $this->assertSame(91.0, $score['value']);
            $this->assertNull($score['change']);
            $this->assertSame('91/100', $score['display_value']);
            $this->assertSame('0.025', collect($data['sources'][0]['metrics'])->firstWhere('key', 'lab_cls')['display_value']);
            $this->assertSame('origin', DailyMetric::where('metric', 'crux_origin_lcp')->sole()->context['scope']);
            $this->assertSame('2026-09-26', DailyMetric::where('metric', 'crux_origin_lcp')->sole()->window_start->toDateString());
            $this->assertNull($source->fresh()->finalized_through);
            $store->persist($source, new CollectionResult(replaceDailyDates: ['2026-10-23' => ['crux_origin_lcp', 'crux_origin_inp', 'crux_origin_cls']]), CarbonImmutable::now());
            $this->assertFalse(DailyMetric::where('metric', 'crux_origin_lcp')->exists());
            $this->assertSame(2, DailyMetric::where('metric', 'lab_score')->count());
            $store->persist($source, new CollectionResult([['metric' => 'crux_origin_lcp', 'date' => '2026-10-23', 'value' => 1800.0, 'is_partial' => false, 'context' => ['type' => 'field', 'scope' => 'origin', 'collection_start' => '2026-09-26', 'collection_end' => '2026-10-23']]]), CarbonImmutable::now());

            return $project;
        });
        $this->actingAs($owner)->get('/w/speed/projects/'.$project->id)->assertOk()->assertSee('Lab tests')->assertSee('Real users')->assertSee('Whole site')->assertSee('2026-09-26')->assertSee('Not enough real-user data yet.')->assertDontSee('class="metric-change"', false);
        $this->get('/w/speed/projects/'.$project->id.'/settings')->assertOk();
    }
}
