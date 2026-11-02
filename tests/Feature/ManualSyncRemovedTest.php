<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

/** Collection runs on the schedule only; users cannot queue it from the project page or settings. */
class ManualSyncRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_editors_cannot_trigger_a_sync(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Acme', 'slug' => 'acme']);
        [$project, $source] = app(WorkspaceContext::class)->run($workspace, function () {
            $project = Project::create(['name' => 'Example', 'slug' => 'example']);

            return [$project, Source::create(['project_id' => $project->id, 'kind' => 'github', 'label' => 'Repository', 'identity_hash' => str_repeat('d', 64), 'config' => ['repository' => 'example/app', 'days' => 30], 'reporting_timezone' => 'UTC', 'enabled' => true])];
        });
        $url = '/w/acme/projects/'.$project->id;

        $this->assertFalse(Route::has('metribay.sources.sync'));
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Repository')->assertDontSee('Sync now')->assertDontSee('/sync"', false);
        $this->get($url.'/settings')->assertOk()->assertSee('Manage data source')->assertDontSee('Sync now')->assertDontSee('/sync"', false);
        $this->post('/w/acme/sources/'.$source->id.'/sync')->assertNotFound();
        Queue::assertNotPushed(CollectSource::class);
    }
}
