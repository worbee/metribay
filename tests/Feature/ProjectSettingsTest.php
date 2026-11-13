<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ProjectSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Settings', 'slug' => 'settings']);
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback);
    }

    private function connection(string $name): Connection
    {
        return $this->scoped(function () use ($name) {
            $connection = new Connection(['kind' => 'umami', 'name' => $name]);
            $connection->workspace_id = app(WorkspaceContext::class)->id();
            $connection->storeSecrets(['base_url' => 'https://analytics.example.com', 'username' => 'fixture', 'password' => 'fixture-secret']);
            $connection->save();

            return $connection;
        });
    }

    public function test_project_index_offers_project_creation_and_a_details_link(): void
    {
        $this->actingAs($this->owner)->get('/w/settings')->assertOk()->assertSee('Create your first project')->assertSee('/w/settings/projects/new', false)->assertDontSee('/sources/new"', false);
        $this->get('/w/settings/projects/new')->assertOk()->assertSee('Create project');
        $response = $this->post('/w/settings/projects', ['name' => 'Example', 'domain' => 'https://example.com/']);
        $project = $this->scoped(fn () => Project::query()->sole());
        $response->assertRedirect('/w/settings/projects/'.$project->id)->assertSessionHas('status');
        $this->assertSame('example.com', $project->domain);
        $this->get('/w/settings')->assertOk()->assertSee('View project details')->assertSee('Add project')->assertSee('No data sources');
    }

    public function test_settings_show_projects_and_connected_accounts_only(): void
    {
        $account = $this->connection('Team Umami');
        $project = $this->scoped(fn () => Project::create(['name' => 'Example', 'slug' => 'example']));
        $source = $this->scoped(fn () => Source::create(['project_id' => $project->id, 'kind' => 'umami', 'label' => 'Website', 'connection_id' => $account->id, 'identity_hash' => str_repeat('c', 64), 'config' => ['website_id' => '11111111-1111-4111-8111-111111111111', 'days' => 30], 'reporting_timezone' => 'UTC']));
        $this->actingAs($this->owner)->get('/w/settings/settings')->assertOk()->assertSee('Connected accounts')->assertSee('Import &amp; export', false)->assertDontSee('tab=sources', false)->assertDontSee('tab=connections', false);
        $this->get('/w/settings/settings?tab=sources')->assertRedirect('/w/settings/settings?tab=projects');
        $this->get('/w/settings/settings?tab=connections&create=1')->assertRedirect('/w/settings/settings?tab=accounts&create=1');
        $this->get('/w/settings/settings?tab=accounts')->assertOk()->assertSee('Team Umami')->assertSee('Used by 1 data source')->assertSee('/w/settings/projects/'.$project->id.'/settings#source-'.$source->id, false)->assertDontSee('fixture-secret');
        $this->get('/w/settings/projects/'.$project->id.'/settings')->assertOk()->assertSee('Data sources')->assertSee('Website')->assertSee('via Team Umami')->assertSee('Add data source')->assertDontSee('fixture-secret');
    }

    public function test_removing_a_used_account_pauses_its_data_sources_and_keeps_their_history(): void
    {
        $used = $this->connection('Used');
        $unused = $this->connection('Unused');
        $project = $this->scoped(fn () => Project::create(['name' => 'Example', 'slug' => 'example']));
        $source = $this->scoped(fn () => Source::create(['project_id' => $project->id, 'kind' => 'umami', 'label' => 'Website', 'connection_id' => $used->id, 'identity_hash' => str_repeat('d', 64), 'config' => ['website_id' => '11111111-1111-4111-8111-111111111111', 'days' => 30], 'reporting_timezone' => 'UTC']));
        $this->scoped(fn () => DailyMetric::create(['source_id' => $source->id, 'metric' => 'visitors', 'date' => '2026-11-01', 'value' => 12, 'collected_at' => now()]));
        $other = User::factory()->create();
        Workspace::create(['owner_id' => $other->id, 'name' => 'Other', 'slug' => 'other']);
        $this->actingAs($other)->delete('/w/settings/connections/'.$used->id)->assertNotFound();

        $this->actingAs($this->owner)->get('/w/settings/settings?tab=accounts')->assertOk()->assertSee('Removing this account pauses 1 data source.')->assertDontSee('Also revoke Metribay’s access at Google');
        $this->delete('/w/settings/connections/'.$used->id)->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Connected account removed. Its saved credentials were deleted. 1 data source is paused until you choose another account; its history stays.');
        $this->delete('/w/settings/connections/'.$unused->id)->assertSessionHas('status', 'Connected account removed. Its saved credentials were deleted.');
        $this->assertDatabaseCount('connections', 0);
        $this->assertDatabaseHas('sources', ['id' => $source->id, 'connection_id' => null, 'enabled' => false, 'version' => 2]);
        $this->assertDatabaseHas('daily_metrics', ['source_id' => $source->id, 'metric' => 'visitors']);
        $this->get('/w/settings/projects/'.$project->id.'/settings')->assertOk()->assertSee('No connected account')->assertSee('Paused without a connected account.');
        $this->get('/w/settings/projects/'.$project->id)->assertOk()->assertSee('Paused: this data source has no connected account.');

        $replacement = $this->connection('Replacement');
        $this->patch('/w/settings/sources/'.$source->id, ['version' => 2, 'label' => 'Website', 'days' => 30, 'connection_id' => $replacement->id, 'enabled' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sources', ['id' => $source->id, 'connection_id' => $replacement->id, 'enabled' => true]);
    }

    public function test_removing_a_github_token_keeps_public_release_collection_running(): void
    {
        $token = $this->scoped(function () {
            $connection = new Connection(['kind' => 'github_releases', 'name' => 'Token']);
            $connection->workspace_id = app(WorkspaceContext::class)->id();
            $connection->storeSecrets(['token' => 'fixture-token']);
            $connection->save();

            return $connection;
        });
        $project = $this->scoped(fn () => Project::create(['name' => 'Example', 'slug' => 'example']));
        $source = $this->scoped(fn () => Source::create(['project_id' => $project->id, 'kind' => 'github_releases', 'label' => 'Releases', 'connection_id' => $token->id, 'identity_hash' => str_repeat('e', 64), 'config' => ['repository' => 'example/app'], 'reporting_timezone' => 'UTC']));

        $this->actingAs($this->owner)->delete('/w/settings/connections/'.$token->id)
            ->assertSessionHas('status', 'Connected account removed. Its saved credentials were deleted.');
        $this->assertDatabaseHas('sources', ['id' => $source->id, 'connection_id' => null, 'enabled' => true]);
    }
}
