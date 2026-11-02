<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\HiddenChart;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\ProjectGroup;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ProjectTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $from;

    private Workspace $to;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->from = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Agency', 'slug' => 'agency']);
        $this->to = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Client', 'slug' => 'client']);
    }

    private function scoped(Workspace $workspace, callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($workspace, $callback(...));
    }

    private function connection(Workspace $workspace, string $name): Connection
    {
        return $this->scoped($workspace, function () use ($name) {
            $connection = new Connection(['kind' => 'umami', 'name' => $name]);
            $connection->workspace_id = app(WorkspaceContext::class)->id();
            $connection->storeSecrets(['base_url' => 'https://analytics.example.com', 'username' => 'fixture', 'password' => 'fixture-secret']);
            $connection->save();

            return $connection;
        });
    }

    private function source(Project $project, ?Connection $connection, string $label, string $hash): Source
    {
        return $this->scoped($this->from, fn () => Source::create(['project_id' => $project->id, 'kind' => 'umami', 'label' => $label, 'connection_id' => $connection?->id, 'identity_hash' => str_repeat($hash, 64), 'config' => ['website_id' => '11111111-1111-4111-8111-111111111111', 'days' => 30], 'reporting_timezone' => 'Europe/Berlin']));
    }

    public function test_project_moves_with_history_and_its_own_accounts(): void
    {
        $own = $this->connection($this->from, 'Shop Umami');
        $shared = $this->connection($this->from, 'Team Umami');
        $this->connection($this->to, 'Shop Umami');
        $this->scoped($this->to, fn () => Project::create(['name' => 'Shop', 'slug' => 'shop']));
        $group = $this->scoped($this->from, fn () => ProjectGroup::create(['name' => 'Legacy']));
        $project = $this->scoped($this->from, fn () => Project::create(['name' => 'Shop', 'slug' => 'shop', 'domain' => 'shop.example.com', 'group_id' => $group->id]));
        $other = $this->scoped($this->from, fn () => Project::create(['name' => 'Blog', 'slug' => 'blog']));
        $website = $this->source($project, $own, 'Website', 'a');
        $team = $this->source($project, $shared, 'Team stats', 'b');
        $this->source($other, $shared, 'Blog stats', 'c');
        $this->scoped($this->from, function () use ($project, $website): void {
            DB::table('daily_metrics')->insert(['workspace_id' => $this->from->id, 'source_id' => $website->id, 'metric' => 'visitors', 'date' => '2026-10-01', 'value' => 42, 'collected_at' => now()]);
            DB::table('period_metrics')->insert(['workspace_id' => $this->from->id, 'source_id' => $website->id, 'metric' => 'visitors', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'value' => 900, 'collected_at' => now()]);
            DB::table('sync_runs')->insert(['workspace_id' => $this->from->id, 'source_id' => $website->id, 'started_at' => now(), 'status' => 'success']);
            ProjectEvent::create(['project_id' => $project->id, 'date' => '2026-10-02', 'title' => 'Launch']);
            ProjectEvent::create(['project_id' => $project->id, 'source_id' => $website->id, 'date' => '2026-10-03', 'title' => 'Release', 'origin' => 'github', 'external_id' => 'run-1']);
            HiddenChart::create(['project_id' => $project->id, 'source_id' => $website->id, 'metric_key' => 'visitors']);
        });

        $response = $this->actingAs($this->owner)->post('/w/agency/projects/'.$project->id.'/move', ['workspace_id' => $this->to->id, 'version' => 1]);

        $moved = $this->scoped($this->to, fn () => Project::query()->where('name', 'Shop')->whereKeyNot($project->id)->latest('id')->firstOrFail());
        $response->assertRedirect('/w/client/projects/'.$moved->id.'/settings')->assertSessionHas('status', 'Project moved to Client. Choose a connected account for 1 data source in this workspace.');
        $this->assertSame(['shop-2', 'shop.example.com', null], [$moved->slug, $moved->domain, $moved->group_id]);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('sources', ['id' => $website->id]);
        $this->assertSame(1, $this->scoped($this->from, fn () => Project::query()->count()));

        $this->scoped($this->to, function () use ($moved, $own, $shared): void {
            $sources = Source::query()->where('project_id', $moved->id)->orderBy('label')->get();
            $this->assertSame(['Team stats', 'Website'], $sources->pluck('label')->all());
            $this->assertNull($sources[0]->connection_id);
            $this->assertSame($own->id, $sources[1]->connection_id);
            $this->assertSame('Europe/Berlin', $sources[1]->reporting_timezone);
            $account = Connection::query()->findOrFail($own->id);
            $this->assertSame(['Shop Umami 2', 'fixture-secret'], [$account->name, $account->reveal()['password']]);
            $this->assertFalse(Connection::query()->whereKey($shared->id)->exists());
            $this->assertSame(42.0, (float) DB::table('daily_metrics')->where('workspace_id', $this->to->id)->where('source_id', $sources[1]->id)->value('value'));
            $this->assertSame(1, DB::table('period_metrics')->where('workspace_id', $this->to->id)->where('source_id', $sources[1]->id)->count());
            $this->assertSame(1, DB::table('sync_runs')->where('workspace_id', $this->to->id)->where('source_id', $sources[1]->id)->count());
            $this->assertSame(['Launch', 'Release'], ProjectEvent::query()->where('project_id', $moved->id)->orderBy('date')->pluck('title')->all());
            $this->assertSame($sources[1]->id, ProjectEvent::query()->where('title', 'Release')->value('source_id'));
            $this->assertSame($sources[1]->id, HiddenChart::query()->where('project_id', $moved->id)->value('source_id'));
        });
        $this->scoped($this->from, function () use ($shared): void {
            $this->assertSame(['Blog stats'], Source::query()->where('connection_id', $shared->id)->pluck('label')->all());
            $this->assertSame(0, DB::table('daily_metrics')->where('workspace_id', $this->from->id)->count());
        });
        $this->get('/w/client/projects/'.$moved->id.'/settings')->assertOk()->assertSee('No connected account')->assertSee('via Shop Umami 2');
    }

    public function test_move_requires_a_current_version_and_an_editable_target(): void
    {
        $project = $this->scoped($this->from, fn () => Project::create(['name' => 'Shop', 'slug' => 'shop']));
        $stranger = User::factory()->create();
        $foreign = Workspace::create(['owner_id' => $stranger->id, 'name' => 'Foreign', 'slug' => 'foreign']);
        $this->actingAs($this->owner)->post('/w/agency/projects/'.$project->id.'/move', ['workspace_id' => $foreign->id, 'version' => 1])->assertSessionHasErrors('workspace_id');
        $this->post('/w/agency/projects/'.$project->id.'/move', ['workspace_id' => $this->from->id, 'version' => 1])->assertSessionHasErrors('workspace_id');
        $this->post('/w/agency/projects/'.$project->id.'/move', ['workspace_id' => $this->to->id, 'version' => 2])->assertStatus(409);
        $this->actingAs($stranger)->post('/w/agency/projects/'.$project->id.'/move', ['workspace_id' => $foreign->id, 'version' => 1])->assertNotFound();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'workspace_id' => $this->from->id]);
    }

    public function test_settings_offer_the_move_only_where_workspaces_are_exposed(): void
    {
        $project = $this->scoped($this->from, fn () => Project::create(['name' => 'Shop', 'slug' => 'shop']));
        $this->actingAs($this->owner)->get('/w/agency/projects/'.$project->id.'/settings')->assertOk()->assertDontSee('Move project');
        Route::middleware('web')->get('/workspaces', fn () => 'workspaces')->name('metribay.workspaces');
        app('router')->getRoutes()->refreshNameLookups();
        $this->get('/w/agency/projects/'.$project->id.'/settings')->assertOk()->assertSee('This project is in Agency.')->assertSee('Move project')
            ->assertSee('<option value="'.$this->to->id.'"', false)->assertDontSee('<option value="'.$this->from->id.'"', false)->assertSee('Manage workspaces')->assertDontSee('New workspace');
    }
}
