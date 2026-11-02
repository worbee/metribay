<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Metribay\Core\Livewire\ProjectCharts;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ProjectRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_refresh_reads_new_stored_metrics_and_keeps_the_period(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Refresh', 'slug' => 'refresh']);
        [$project, $source] = app(WorkspaceContext::class)->run($workspace, function () {
            $project = Project::create(['name' => 'Example', 'slug' => 'example']);
            $source = Source::create(['project_id' => $project->id, 'kind' => 'wporg', 'label' => 'Plugin', 'config' => ['slug' => 'example'], 'reporting_timezone' => 'UTC', 'identity_hash' => str_repeat('a', 64), 'last_success_at' => now()]);

            return [$project, $source];
        });
        $this->actingAs($owner)->get('/w/refresh')->assertOk()->assertSee('data-hourly-refresh', false)->assertDontSee('wire:poll.15s', false);
        $this->get('/w/refresh/projects/'.$project->id.'?days=7')->assertOk()->assertSee('data-hourly-refresh', false)->assertSee('new-event');
        $component = Livewire::test(ProjectCharts::class, ['workspaceId' => $workspace->id, 'projectId' => $project->id, 'days' => 7])->assertSee('No data for this period');
        app(WorkspaceContext::class)->run($workspace, fn () => DailyMetric::create(['source_id' => $source->id, 'metric' => 'downloads', 'date' => now()->toDateString(), 'value' => 1234, 'is_partial' => false, 'collected_at' => now()]));
        $component->call('$refresh')->assertSet('days', 7)->assertSee('data-metric-chart', false)->assertSee('1234');
        Http::assertNothingSent();
    }

    public function test_project_refresh_rechecks_workspace_access_and_project_scope(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Own', 'slug' => 'own']);
        $other = Workspace::create(['owner_id' => User::factory()->create()->id, 'name' => 'Other', 'slug' => 'other']);
        $foreign = app(WorkspaceContext::class)->run($other, fn () => Project::create(['name' => 'Private project', 'slug' => 'private']));
        $this->actingAs($owner);
        Livewire::test(ProjectCharts::class, ['workspaceId' => $other->id, 'projectId' => $foreign->id])->assertNotFound();
        Livewire::test(ProjectCharts::class, ['workspaceId' => $workspace->id, 'projectId' => $foreign->id])->assertNotFound();
        $own = app(WorkspaceContext::class)->run($workspace, fn () => Project::create(['name' => 'Own project', 'slug' => 'own-project']));
        $component = Livewire::test(ProjectCharts::class, ['workspaceId' => $workspace->id, 'projectId' => $own->id])->assertSee('Add your first data source');
        $workspace->update(['owner_id' => $other->owner_id]);
        $component->call('$refresh')->assertNotFound();
    }
}
