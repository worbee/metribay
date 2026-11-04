<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\HiddenChart;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\ProjectGroup;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\DataDeletion;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class DataDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const HISTORY_TABLES = ['daily_metrics', 'period_metrics', 'search_queries', 'search_query_months', 'sync_runs', 'project_events', 'hidden_charts'];

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Studio', 'slug' => 'studio']);
    }

    private function scoped(Workspace $workspace, callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($workspace, $callback(...));
    }

    private function connection(Workspace $workspace): Connection
    {
        return $this->scoped($workspace, function () {
            $connection = new Connection(['kind' => 'umami', 'name' => 'Team Umami']);
            $connection->workspace_id = app(WorkspaceContext::class)->id();
            $connection->storeSecrets(['base_url' => 'https://analytics.example.com', 'username' => 'fixture', 'password' => 'fixture-secret']);
            $connection->save();

            return $connection;
        });
    }

    /** A project with one data source and a row in every history table. */
    private function projectWithHistory(Workspace $workspace, Connection $connection, string $name, string $hash): Project
    {
        return $this->scoped($workspace, function () use ($workspace, $connection, $name, $hash): Project {
            $project = Project::create(['name' => $name, 'slug' => strtolower($name), 'domain' => strtolower($name).'.example']);
            $source = Source::create(['project_id' => $project->id, 'kind' => 'umami', 'label' => 'Website', 'connection_id' => $connection->id, 'identity_hash' => str_repeat($hash, 64), 'config' => ['website_id' => '11111111-1111-4111-8111-111111111111', 'days' => 30]]);
            $row = ['workspace_id' => $workspace->id, 'source_id' => $source->id];
            DB::table('daily_metrics')->insert($row + ['metric' => 'visitors', 'date' => '2026-10-01', 'value' => 42, 'collected_at' => now()]);
            DB::table('daily_metrics')->insert($row + ['metric' => 'visitors', 'date' => '2026-10-02', 'value' => 12.5, 'is_partial' => true, 'collected_at' => now()]);
            DB::table('period_metrics')->insert($row + ['metric' => 'unique_visitors', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'value' => 900, 'collected_at' => now()]);
            DB::table('search_queries')->insert($row + ['days' => 28, 'start_date' => '2026-10-01', 'end_date' => '2026-10-28', 'query' => 'fixture', 'clicks' => 3, 'impressions' => 40, 'collected_at' => now()]);
            $month = DB::table('search_query_months')->insertGetId($row + ['month' => '2026-09-01', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'query_count' => 1, 'collected_at' => now()]);
            DB::table('search_query_month_rows')->insert(['search_query_month_id' => $month, 'query' => 'fixture', 'clicks' => 3, 'impressions' => 40]);
            DB::table('sync_runs')->insert($row + ['started_at' => now(), 'status' => 'success']);
            ProjectEvent::create(['project_id' => $project->id, 'date' => '2026-10-02', 'title' => 'Launch']);
            HiddenChart::create(['project_id' => $project->id, 'source_id' => $source->id, 'metric_key' => 'visitors']);

            return $project;
        });
    }

    private function rows(string $table, Workspace $workspace): int
    {
        return DB::table($table)->where('workspace_id', $workspace->id)->count();
    }

    public function test_owner_deletes_a_project_with_all_of_its_history(): void
    {
        $connection = $this->connection($this->workspace);
        $shop = $this->projectWithHistory($this->workspace, $connection, 'Shop', 'a');
        $blog = $this->projectWithHistory($this->workspace, $connection, 'Blog', 'b');

        $this->actingAs($this->owner)->delete('/w/studio/projects/'.$shop->id)
            ->assertRedirect('/w/studio/settings?tab=projects')->assertSessionHas('status', 'Project Shop deleted.');

        $this->assertDatabaseMissing('projects', ['id' => $shop->id]);
        $this->assertSame(1, DB::table('sources')->where('workspace_id', $this->workspace->id)->count());
        $this->assertSame(['blog'], DB::table('projects')->pluck('slug')->all());
        foreach (self::HISTORY_TABLES as $table) {
            $this->assertSame($table === 'daily_metrics' ? 2 : 1, $this->rows($table, $this->workspace), $table);
        }
        $this->assertSame(1, DB::table('search_query_month_rows')->count());
        $this->assertDatabaseHas('connections', ['id' => $connection->id]);
        $this->assertDatabaseHas('projects', ['id' => $blog->id]);
    }

    public function test_only_the_workspace_owner_can_delete_a_project(): void
    {
        $project = $this->projectWithHistory($this->workspace, $this->connection($this->workspace), 'Shop', 'a');
        $stranger = User::factory()->create();

        $status = $this->actingAs($stranger)->delete('/w/studio/projects/'.$project->id)->status();

        $this->assertContains($status, [403, 404]);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertSame(2, $this->rows('daily_metrics', $this->workspace));
    }

    public function test_project_settings_warn_that_deleting_removes_all_history(): void
    {
        $project = $this->scoped($this->workspace, fn () => Project::create(['name' => 'Shop', 'slug' => 'shop']));

        $this->actingAs($this->owner)->get('/w/studio/projects/'.$project->id.'/settings')->assertOk()
            ->assertSee('Delete project')
            ->assertSee('This cannot be undone, and some history, such as uptime and page speed tests, cannot be collected again.')
            ->assertSee('data-confirm="Delete Shop and all of its historic data? This cannot be undone."', false)
            ->assertSee('action="'.route('metribay.projects.destroy', ['workspace' => 'studio', 'project' => $project->id]).'"', false);
    }

    public function test_workspace_erase_removes_every_project_account_and_group_but_keeps_the_workspace(): void
    {
        $other = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Client', 'slug' => 'client']);
        $this->projectWithHistory($this->workspace, $this->connection($this->workspace), 'Shop', 'a');
        $this->scoped($this->workspace, fn () => ProjectGroup::create(['name' => 'Legacy']));
        $this->projectWithHistory($other, $this->connection($other), 'Kept', 'c');

        $result = app(DataDeletion::class)->workspace($this->workspace);

        $this->assertSame(['projects' => 1, 'connections' => 1], $result);
        $this->assertDatabaseHas('workspaces', ['id' => $this->workspace->id]);
        foreach ([...self::HISTORY_TABLES, 'projects', 'sources', 'connections', 'project_groups'] as $table) {
            $this->assertSame(0, $this->rows($table, $this->workspace), $table);
        }
        $this->assertSame(1, DB::table('search_query_month_rows')->count());
        $this->assertSame(2, $this->rows('daily_metrics', $other));
        $this->assertSame(1, $this->rows('connections', $other));
    }

    public function test_metric_history_exports_as_csv_for_the_current_workspace_only(): void
    {
        $connection = $this->connection($this->workspace);
        $project = $this->projectWithHistory($this->workspace, $connection, 'Shop', 'a');
        $this->scoped($this->workspace, fn () => $project->forceFill(['name' => '=Shop'])->save());
        $other = Workspace::create(['owner_id' => User::factory()->create()->id, 'name' => 'Client', 'slug' => 'client']);
        $this->projectWithHistory($other, $this->connection($other), 'Secret', 'c');

        $response = $this->actingAs($this->owner)->get('/w/studio/export/history')->assertOk();

        $this->assertStringContainsString('attachment; filename=metribay-history-studio-', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame([
            'project,domain,data_source,provider,metric,start_date,end_date,value,partial,currency',
            "'=Shop,shop.example,Website,umami,visitors,2026-10-01,2026-10-01,42,no,",
            "'=Shop,shop.example,Website,umami,visitors,2026-10-02,2026-10-02,12.5,yes,",
            "'=Shop,shop.example,Website,umami,unique_visitors,2026-10-01,2026-10-31,900,no,",
        ], explode("\n", trim($response->streamedContent())));
    }

    public function test_workspace_settings_offer_the_history_export(): void
    {
        $this->actingAs($this->owner)->get('/w/studio/settings?tab=data')->assertOk()
            ->assertSee('Export metric history')->assertSee(route('metribay.settings.export-history', 'studio'), false);
    }
}
