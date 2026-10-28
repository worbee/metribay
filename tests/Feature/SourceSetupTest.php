<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class SourceSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_resumes_and_creates_project_only_on_confirmation(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $this->actingAs($owner)->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'wporg'])->assertRedirect('/w/setup/sources/new');
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Search plugins');
        $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => 'synthetic-plugin', 'timezone' => 'UTC'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('projects', 0);
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Connect and start import');
        $this->post('/w/setup/sources/setup', ['action' => 'finish', 'name' => 'Example', 'domain' => 'example.com', 'days' => 180])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseHas('sources', ['kind' => 'wporg', 'workspace_id' => $workspace->id, 'enabled' => true]);
        Queue::assertPushed(CollectSource::class, fn ($job) => $job->workspaceId === $workspace->id && $job->full);
        $this->post('/w/setup/sources/setup', ['action' => 'finish', 'name' => 'Duplicate', 'days' => 180])->assertStatus(409);
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_discovery_uses_saved_account_and_server_listed_resources(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/api/auth/login' => Http::response(['token' => 'fixture']), '*/api/websites*' => Http::response(['data' => [['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Example', 'domain' => 'example.com']], 'count' => 1])]);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $connection = app(WorkspaceContext::class)->run($workspace, function () {
            $connection = new Connection(['workspace_id' => app(WorkspaceContext::class)->id(), 'kind' => 'umami', 'name' => 'Example account']);
            $connection->storeSecrets(['base_url' => 'https://analytics.example.com', 'username' => 'fixture', 'password' => 'fixture-secret']);
            $connection->save();

            return $connection;
        });
        $this->actingAs($owner)->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'umami']);
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'connection_id' => $connection->id]);
        $this->post('/w/setup/sources/setup', ['action' => 'discover'])->assertSessionHasNoErrors();
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('example.com')->assertDontSee('fixture-secret');
        $this->post('/w/setup/sources/setup', ['action' => 'resource', 'resource' => 99])->assertUnprocessable();
        $this->post('/w/setup/sources/setup', ['action' => 'resource', 'resource' => 0])->assertSessionHasNoErrors();
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Connect and start import');
        Http::assertSentCount(4);
    }

    public function test_workspace_boundary_is_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        Workspace::create(['owner_id' => $other->id, 'name' => 'Other', 'slug' => 'other']);
        $this->actingAs($owner)->get('/w/other/sources/new')->assertNotFound();
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'umami']);
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'connection_id' => 999])->assertNotFound();
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_google_callback_rejects_unbound_state(): void
    {
        $owner = User::factory()->create();
        Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $this->actingAs($owner)->get('/w/setup/sources/google/callback?state=forged&code=fixture')->assertForbidden();
        $this->assertDatabaseCount('connections', 0);
    }

    public function test_existing_project_context_survives_first_provider_selection(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = app(WorkspaceContext::class)->run($workspace, fn () => Project::create(['name' => 'Existing', 'slug' => 'existing']));
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id)->assertOk();
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'wporg']);
        $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => 'synthetic-plugin', 'timezone' => 'UTC']);
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('value="'.$project->id.'" selected', false);
    }

    public function test_google_browser_authorization_binds_and_consumes_state(): void
    {
        config(['metribay.google.client_id' => 'fixture-id', 'metribay.google.client_secret' => 'fixture-secret']);
        Http::preventStrayRequests();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['refresh_token' => 'fixture-refresh'])]);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $this->actingAs($owner)->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'ga4']);
        $this->get('/w/setup/sources/google')->assertRedirectContains('https://accounts.google.com/');
        $state = session('metribay_google.state');
        $this->get('/integrations/google/callback?state='.$state.'&code=fixture-code')->assertRedirect('/w/setup/sources/new');
        $this->assertDatabaseCount('connections', 1);
        app(WorkspaceContext::class)->run($workspace, function () {
            $this->assertSame('fixture-refresh', Connection::first()->reveal()['refresh_token']);
        });
        $this->get('/w/setup/sources/google/callback?state='.$state.'&code=fixture-code')->assertForbidden();
    }

    public function test_validation_preserves_explicit_new_project_choice(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = app(WorkspaceContext::class)->run($workspace, fn () => Project::create(['name' => 'Existing', 'slug' => 'existing']));
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'wporg']);
        $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => 'synthetic-plugin', 'timezone' => 'UTC']);
        $this->from('/w/setup/sources/new')->post('/w/setup/sources/setup', ['action' => 'finish', 'project_id' => '', 'name' => 'New project', 'domain' => 'example.com/path', 'days' => 180])->assertSessionHasErrors('domain');
        $this->get('/w/setup/sources/new')->assertOk()->assertDontSee('value="'.$project->id.'" selected', false)->assertSee('value="New project"', false);
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_archived_project_has_no_add_source_cta(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = app(WorkspaceContext::class)->run($workspace, fn () => Project::create(['name' => 'Archived', 'slug' => 'archived', 'archived' => true]));
        $this->actingAs($owner)->get('/w/setup/projects/'.$project->id)->assertOk()->assertDontSee('/sources/new?project=', false);
    }
}
