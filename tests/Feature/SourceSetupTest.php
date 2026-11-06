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
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class SourceSetupTest extends TestCase
{
    use RefreshDatabase;

    private function projectIn(Workspace $workspace, string $name = 'Example'): Project
    {
        return app(WorkspaceContext::class)->run($workspace, fn () => Project::create(['name' => $name, 'slug' => strtolower($name)]));
    }

    public function test_setup_adds_a_data_source_to_the_chosen_project_only_on_confirmation(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id)->assertOk()->assertSee('Where is the data?')->assertDontSee('Save for later');
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'wporg'])->assertRedirect('/w/setup/sources/new');
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Search plugins');
        $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => 'synthetic-plugin', 'timezone' => 'UTC'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sources', 0);
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Add and start import')->assertSee('Example');
        $this->post('/w/setup/sources/setup', ['action' => 'finish', 'days' => 180])->assertRedirect('/w/setup/projects/'.$project->id)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseHas('sources', ['kind' => 'wporg', 'workspace_id' => $workspace->id, 'project_id' => $project->id, 'enabled' => true]);
        Queue::assertPushed(CollectSource::class, fn ($job) => $job->workspaceId === $workspace->id && $job->full);
        $this->post('/w/setup/sources/setup', ['action' => 'finish', 'days' => 180])->assertStatus(409);
        $this->assertDatabaseCount('sources', 1);
    }

    public function test_setup_is_never_resumed_after_leaving_and_each_click_starts_fresh(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'wporg']);
        $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => 'synthetic-plugin', 'timezone' => 'UTC']);
        $this->get('/w/setup/sources/new?project='.$project->id)->assertOk()->assertSee('Where is the data?')->assertDontSee('Add and start import');
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'wporg']);
        $this->get('/w/setup/projects/'.$project->id)->assertOk();
        $this->assertNull(session('source_setup'));
        $this->get('/w/setup/sources/new')->assertRedirect('/w/setup');
        $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => 'synthetic-plugin', 'timezone' => 'UTC'])->assertStatus(409);
        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('connections', 0);
    }

    public function test_saving_an_account_is_an_explicit_choice_kept_after_leaving(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/api/auth/login' => Http::response(['password' => 'private-provider-body'], 401)]);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'umami']);
        $this->get('/w/setup/sources/new')->assertSee('Save account and continue')->assertSee('Settings → Connected accounts');
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'values' => ['base_url' => 'https://analytics.example.com', 'username' => 'fixture', 'password' => 'fixture-secret']]);
        $this->get('/w/setup')->assertOk();
        $this->assertDatabaseCount('connections', 1);
        $this->assertDatabaseCount('sources', 0);
        $this->get('/w/setup/settings?tab=accounts')->assertOk()->assertSee('Not used yet')->assertDontSee('fixture-secret');
    }

    public function test_discovery_uses_saved_account_and_server_listed_resources(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/api/me/teams*' => Http::response(['data' => [], 'count' => 0]), '*/api/auth/login' => Http::response(['token' => 'fixture']), '*/api/websites*' => Http::response(['data' => [['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Example', 'domain' => 'example.com']], 'count' => 1])]);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $connection = app(WorkspaceContext::class)->run($workspace, function () {
            $connection = new Connection(['workspace_id' => app(WorkspaceContext::class)->id(), 'kind' => 'umami', 'name' => 'Example account']);
            $connection->storeSecrets(['base_url' => 'https://analytics.example.com', 'username' => 'fixture', 'password' => 'fixture-secret']);
            $connection->save();

            return $connection;
        });
        $project = $this->projectIn($workspace);
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'umami']);
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'connection_id' => $connection->id]);
        $this->post('/w/setup/sources/setup', ['action' => 'discover'])->assertSessionHasNoErrors();
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('example.com')->assertDontSee('fixture-secret');
        $this->post('/w/setup/sources/setup', ['action' => 'resource', 'resource' => 99])->assertUnprocessable();
        $this->post('/w/setup/sources/setup', ['action' => 'resource', 'resource' => 0])->assertSessionHasNoErrors();
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Add and start import');
        Http::assertSentCount(6);
    }

    public function test_umami_discovery_errors_are_actionable_without_exposing_provider_bodies(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/api/auth/login' => Http::response(['password' => 'private-provider-body'], 401)]);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        $owner = User::factory()->create();
        $project = $this->projectIn(Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']));
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'umami']);
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'values' => ['base_url' => 'https://analytics.example.com', 'username' => 'fixture', 'password' => 'fixture-secret']]);
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Umami sign-in failed')->assertDontSee('private-provider-body');
        $this->post('/w/setup/sources/setup', ['action' => 'discover'])->assertSessionHasErrors(['resource' => 'Umami sign-in failed. Check the connected account’s username and password. (HTTP 401)']);
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Umami sign-in failed')->assertDontSee('private-provider-body');
    }

    public function test_workspace_boundary_is_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->projectIn(Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']));
        $foreign = $this->projectIn(Workspace::create(['owner_id' => $other->id, 'name' => 'Other', 'slug' => 'other']), 'Foreign');
        $this->actingAs($owner)->get('/w/other/sources/new?project='.$foreign->id)->assertNotFound();
        $this->get('/w/setup/sources/new?project='.$foreign->id)->assertNotFound();
        $this->get('/w/setup/sources/new?project='.$project->id)->assertOk();
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'umami']);
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'connection_id' => 999])->assertNotFound();
        $this->assertDatabaseCount('sources', 0);
    }

    public function test_google_callback_rejects_unbound_state(): void
    {
        $owner = User::factory()->create();
        Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $this->actingAs($owner)->get('/w/setup/sources/google/callback?state=forged&code=fixture')->assertForbidden();
        $this->assertDatabaseCount('connections', 0);
    }

    public function test_google_browser_authorization_binds_and_consumes_state(): void
    {
        config(['metribay.google.client_id' => 'fixture-id', 'metribay.google.client_secret' => 'fixture-secret']);
        Http::preventStrayRequests();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['refresh_token' => 'fixture-refresh'])]);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'ga4']);
        $this->get('/w/setup/sources/google')->assertRedirectContains('https://accounts.google.com/');
        $state = session('metribay_google.state');
        $this->get('/integrations/google/callback?state='.$state.'&code=fixture-code')->assertRedirect('/w/setup/sources/new');
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Select what to track');
        $this->assertDatabaseCount('connections', 1);
        app(WorkspaceContext::class)->run($workspace, function () {
            $this->assertSame('fixture-refresh', Connection::first()->reveal()['refresh_token']);
            $this->assertStringStartsWith('GA4 · ', Connection::first()->name);
        });
        $this->get('/w/setup/sources/google/callback?state='.$state.'&code=fixture-code')->assertForbidden();
    }

    public function test_connecting_another_account_is_secondary_once_one_is_saved(): void
    {
        config(['metribay.google.client_id' => 'fixture-id', 'metribay.google.client_secret' => 'fixture-secret']);
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'gsc']);
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('button button-primary" href="'.route('metribay.sources.google', 'setup'), false)->assertDontSee('Or connect another account')->assertDontSee('Use a connected account');
        app(WorkspaceContext::class)->run($workspace, function (): void {
            $connection = new Connection(['workspace_id' => app(WorkspaceContext::class)->id(), 'kind' => 'gsc', 'name' => 'Example Google']);
            $connection->storeSecrets(['client_id' => 'fixture-id', 'client_secret' => 'fixture-secret', 'refresh_token' => 'fixture-refresh']);
            $connection->save();
        });
        $this->get('/w/setup/sources/new')->assertOk()
            ->assertSeeInOrder(['Use a connected account', 'Example Google', 'Continue', 'Or connect another account', 'Connect with your Google app', 'Connect an account with your own credentials'])
            ->assertSee('button button-secondary" href="'.route('metribay.sources.google', 'setup'), false)
            ->assertDontSee('fixture-refresh');
    }

    public function test_finish_validation_keeps_the_setup_open(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace, 'Existing');
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'wporg']);
        $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => 'synthetic-plugin', 'timezone' => 'UTC']);
        $this->from('/w/setup/sources/new')->post('/w/setup/sources/setup', ['action' => 'finish', 'days' => 0])->assertSessionHasErrors('days');
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Add and start import')->assertSee('Existing');
        $this->assertDatabaseCount('sources', 0);
    }

    public function test_archived_project_has_no_add_source_cta(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = app(WorkspaceContext::class)->run($workspace, fn () => Project::create(['name' => 'Archived', 'slug' => 'archived', 'archived' => true]));
        $this->actingAs($owner)->get('/w/setup/projects/'.$project->id)->assertOk()->assertDontSee('/sources/new?project=', false);
    }

    public function test_github_release_downloads_can_be_added_for_a_public_repository_without_a_token(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'github_releases'])->assertRedirect();
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Continue without a token')->assertSee('Personal access token');
        $this->post('/w/setup/sources/setup', ['action' => 'skip'])->assertSessionHasNoErrors();
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('owner/repository')->assertDontSee('Find available items');
        $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => 'example/repo', 'timezone' => 'UTC'])->assertSessionHasNoErrors();
        $this->post('/w/setup/sources/setup', ['action' => 'finish', 'days' => 180])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sources', ['kind' => 'github_releases', 'project_id' => $project->id, 'connection_id' => null, 'reporting_timezone' => 'UTC']);
        $this->assertDatabaseCount('connections', 0);
        Queue::assertPushed(CollectSource::class);
    }

    public function test_github_release_downloads_keep_an_optional_token(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'github_releases']);
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'values' => ['token' => 'synthetic-token']])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('connections', 1);
        $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => 'example/repo', 'timezone' => 'UTC']);
        $this->post('/w/setup/sources/setup', ['action' => 'finish', 'days' => 180])->assertSessionHasNoErrors();
        app(WorkspaceContext::class)->run($workspace, function (): void {
            $connection = Connection::query()->firstOrFail();
            $source = Source::query()->firstOrFail();
            $this->assertSame('github_releases', $connection->kind);
            $this->assertSame($connection->id, $source->connection_id);
            $this->assertSame(['repository' => 'example/repo'], $source->config);
        });
    }
}
