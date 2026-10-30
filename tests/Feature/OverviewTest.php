<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Livewire\Dashboard;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\MetricStore;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Overview', 'slug' => 'overview']);
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback);
    }

    /** @param list<array{0: string, 1: int, 2: float}> $rows metric, days ago, value */
    private function source(Project $project, string $kind, string $label, array $rows): Source
    {
        $now = CarbonImmutable::now('UTC');
        $source = Source::create(['project_id' => $project->id, 'kind' => $kind, 'label' => $label, 'config' => [], 'identity_hash' => hash('sha256', $project->id.$label), 'reporting_timezone' => 'UTC', 'last_success_at' => $now, 'latest_data_date' => $now->toDateString()]);
        app(MetricStore::class)->persist($source, new CollectionResult(array_map(fn (array $row): array => ['metric' => $row[0], 'date' => $now->subDays($row[1])->toDateString(), 'value' => $row[2], 'is_partial' => false], $rows)), $now);

        return $source;
    }

    private function fixtures(): void
    {
        $this->scoped(function () {
            $busy = Project::create(['name' => 'Busy', 'slug' => 'busy', 'domain' => 'busy.example.test']);
            $this->source($busy, 'umami', 'Website', [['visits', 1, 10.0], ['visits', 2, 5.0], ['visits', 9, 6.0], ['visitors', 1, 8.0]]);
            $this->source($busy, 'umami', 'Application', [['visits', 1, 4.0], ['visits', 9, 4.0]]);
            $this->source($busy, 'gsc', 'Google', [['clicks', 1, 7.0]]);
            $this->source($busy, 'bing', 'Bing', [['clicks', 2, 2.0]]);
            $this->source($busy, 'github', 'Repository', [['commits', 1, 12.0], ['prs_merged', 1, 3.0]]);
            $this->source($busy, 'uptimerobot', 'Production', [['monitor_status', 0, 2.0], ['uptime_24h', 0, 100.0]]);
            $this->source($busy, 'uptimerobot', 'Staging', [['monitor_status', 0, 9.0], ['uptime_24h', 0, 91.5]]);
            $quiet = Project::create(['name' => 'Quiet', 'slug' => 'quiet', 'domain' => 'quiet.example.test']);
            $this->source($quiet, 'umami', 'Website', [['visits', 1, 40.0]]);
            Project::create(['name' => 'Empty', 'slug' => 'empty']);
        });
    }

    public function test_each_project_gets_one_combined_value_per_kind_of_data(): void
    {
        $this->fixtures();
        $projects = collect($this->scoped(fn () => app(DashboardData::class)->projects(7)))->keyBy('name');
        $busy = $projects['Busy']['summary'];

        $this->assertSame(19.0, $busy['traffic']['value']);
        $this->assertSame('visits', $busy['traffic']['key']);
        $this->assertSame(10.0, $busy['traffic']['previous']);
        $this->assertEqualsWithDelta(90.0, $busy['traffic']['change'], 0.001);
        $this->assertCount(7, $busy['traffic']['series']);
        $this->assertSame(14.0, $busy['traffic']['series'][5]);
        $this->assertNull($busy['traffic']['series'][0]);
        $this->assertSame(['Website', 'Application'], array_column($busy['traffic']['parts'], 'label'));
        $this->assertSame(9.0, $busy['search']['value']);
        $this->assertSame(12.0, $busy['development']['value']);
        $this->assertSame(3.0, $busy['development']['secondary']);
        $this->assertSame(['value' => 91.5, 'up' => 1, 'down' => 1, 'total' => 2, 'state' => 'down'], array_intersect_key($busy['uptime'], array_flip(['up', 'down', 'total', 'state', 'value'])));
        $this->assertArrayNotHasKey('downloads', $busy);
        $this->assertSame('failed', $projects['Busy']['status']);
        $this->assertNull($projects['Quiet']['summary']['traffic']['change']);
        $this->assertSame([], $projects['Empty']['summary']);
        $this->assertSame('empty', $projects['Empty']['status']);
    }

    public function test_overview_lists_projects_as_compact_rows_with_attention_first(): void
    {
        $this->fixtures();
        $html = $this->actingAs($this->owner)->get('/w/overview?days=7')->assertOk()
            ->assertSee('Visits')->assertSee('Search clicks')->assertSee('Commits · PRs')->assertSee('Uptime · 24h')->assertDontSee('>Downloads<', false)
            ->assertSee('View project details')->assertSee('No data sources')->assertSee('1 data source needs attention')
            ->assertSee('Totals across all projects')->assertSee('class="sparkline"', false)->assertSee('1/2')
            ->getContent();

        $this->assertTrue(strpos($html, '>Busy<') < strpos($html, '>Empty<') && strpos($html, '>Empty<') < strpos($html, '>Quiet<'));
    }

    public function test_columns_sort_by_their_headline_value_with_unknown_values_last(): void
    {
        $this->fixtures();
        $this->actingAs($this->owner);
        $component = Livewire::test(Dashboard::class, ['workspaceId' => $this->workspace->id])->set('days', 7)->call('sortBy', 'traffic');
        $component->assertSet('sort', 'traffic')->assertSet('direction', 'desc')->assertSeeInOrder(['>Quiet<', '>Busy<', '>Empty<'], false);
        $component->call('sortBy', 'traffic')->assertSet('direction', 'asc')->assertSeeInOrder(['>Busy<', '>Quiet<', '>Empty<'], false);
        $component->call('sortBy', 'name')->assertSet('direction', 'asc')->assertSeeInOrder(['>Busy<', '>Empty<', '>Quiet<'], false);
        $component->call('sortBy', 'unknown')->assertSet('sort', 'name');
    }
}
