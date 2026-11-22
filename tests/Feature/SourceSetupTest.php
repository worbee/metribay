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
use Metribay\Core\Services\ProviderCatalog;
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
        $this->post('/w/setup/sources/setup', ['action' => 'finish', 'days' => 180])->assertRedirect('/w/setup')->assertSessionHas('status');
        $this->assertDatabaseCount('sources', 1);
    }

    public function test_providers_are_grouped_with_their_purpose_and_setup_guides(): void
    {
        config(['metribay.marketing_url' => 'https://docs.example.test/']);
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        app(WorkspaceContext::class)->run($workspace, fn () => Source::create(['project_id' => $project->id, 'kind' => 'wporg', 'label' => 'Synthetic plugin', 'config' => ['slug' => 'synthetic-plugin'], 'reporting_timezone' => 'UTC', 'identity_hash' => str_repeat('b', 64), 'enabled' => true]));

        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id)->assertOk()
            ->assertSeeInOrder(['Website analytics', 'Google Analytics 4', 'Search engines', 'Google Search Console', 'Uptime and speed', 'Publishing', 'Code and releases', 'WordPress.org', 'Social followers', 'Revenue', 'Paddle Billing'])
            ->assertSee('How often your pages appear in Google and Bing results', false)
            ->assertSee('Clicks and impressions in Google Search')
            ->assertSee('1 added')
            ->assertSee('No sign-in needed')
            ->assertSee('href="https://docs.example.test/docs/integrations/search-console/"', false)
            ->assertSee('href="https://docs.example.test/docs/integrations/wordpress/"', false)
            ->assertSee('data-provider-search', false);

        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'gsc']);
        $this->get('/w/setup/sources/new')->assertOk()
            ->assertSee('Read the Google Search Console setup guide')
            ->assertSee('href="https://docs.example.test/docs/integrations/search-console/"', false)
            ->assertDontSee('Website analytics');

        $owner->forceFill(['locale' => 'de'])->save();
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('href="https://docs.example.test/de/docs/integrations/search-console/"', false);
    }

    public function test_provider_groups_keep_unlisted_providers_and_drop_empty_groups(): void
    {
        $grouped = ProviderCatalog::grouped(['gsc', 'ga4', 'future_provider']);
        $this->assertSame(['analytics' => ['ga4'], 'search' => ['gsc'], 'other' => ['future_provider']], $grouped);
        $this->assertSame([], array_diff(array_keys(ProviderCatalog::all()), array_merge(...array_values(ProviderCatalog::GROUPS))));
        foreach (array_keys(ProviderCatalog::all()) as $kind) {
            $this->assertNotNull(ProviderCatalog::docsUrl($kind, 'en'), $kind);
        }
        $this->assertNull(ProviderCatalog::docsUrl('future_provider'));
        $this->assertSame('https://metribay.com/docs/integrations/umami/', ProviderCatalog::docsUrl('umami', 'xx'));
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
        $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => 'synthetic-plugin', 'timezone' => 'UTC'])->assertRedirect('/w/setup')->assertSessionHas('status');
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

    public function test_follower_counts_are_added_from_a_public_account_without_a_connected_account(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        foreach (['bluesky' => ['https://bsky.app/profile/Example.bsky.social', 'example.bsky.social', 'Bluesky handle'], 'mastodon' => ['@Example@Social.example', 'example@social.example', 'Mastodon account']] as $kind => [$input, $stored, $label]) {
            $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
            $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => $kind])->assertRedirect();
            $this->get('/w/setup/sources/new')->assertOk()->assertSee($label)->assertDontSee('Connected account')->assertDontSee('Find available items');
            $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => 'not an account', 'timezone' => 'UTC'])->assertSessionHasErrors('resource_id');
            $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => $input, 'timezone' => 'UTC'])->assertSessionHasNoErrors();
            $this->get('/w/setup/sources/new')->assertOk()->assertSee($stored)->assertSee('reports only the current follower count')->assertDontSee('History window (days)');
            $this->post('/w/setup/sources/setup', ['action' => 'finish', 'days' => 180])->assertSessionHasNoErrors();
            app(WorkspaceContext::class)->run($workspace, function () use ($kind, $stored): void {
                $source = Source::query()->where('kind', $kind)->firstOrFail();
                $this->assertSame(['account' => $stored], $source->config);
                $this->assertNull($source->connection_id);
                $this->assertSame('UTC', $source->reporting_timezone);
            });
            $this->get('/w/setup/sources/new?project='.$project->id);
            $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => $kind]);
            $this->post('/w/setup/sources/setup', ['action' => 'manual', 'resource_id' => strtoupper($stored), 'timezone' => 'UTC'])->assertSessionHasErrors('resource');
        }
        $this->assertDatabaseCount('connections', 0);
        Queue::assertPushed(CollectSource::class, 2);
    }

    public function test_posthog_adds_usage_and_key_event_sources_for_the_same_project(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake([
            'us.posthog.com/api/projects/48213/' => Http::response(['id' => 48213, 'name' => 'Synthetic app', 'timezone' => 'Europe/Berlin']),
            'us.posthog.com/api/projects/*' => Http::response(['results' => [['id' => 48213, 'name' => 'Synthetic app']], 'next' => null]),
        ]);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'posthog']);
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('not the project key that starts with phc_');
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'values' => ['base_url' => 'https://us.posthog.com', 'api_key' => 'phc_ingestion_only']])->assertSessionHasErrors('values.api_key');
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'values' => ['base_url' => 'https://us.i.posthog.com', 'api_key' => 'phx_synthetic']])->assertSessionHasNoErrors();
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Synthetic app')->assertDontSee('phx_synthetic');
        $added = [];
        foreach ([['track' => 'usage'], ['track' => 'event', 'event' => 'user_signed_up'], ['track' => 'event', 'event' => 'invoice_sent']] as $choice) {
            $this->post('/w/setup/sources/setup', ['action' => 'resource', 'resource' => 0])->assertSessionHasNoErrors();
            $this->get('/w/setup/sources/new')->assertOk()->assertSee('What to track')->assertSee('Reporting timezone: Europe/Berlin');
            $this->post('/w/setup/sources/setup', ['action' => 'finish', 'days' => 180] + $choice)->assertSessionHasNoErrors();
            $added[] = $choice;
            $this->get('/w/setup/sources/new?project='.$project->id);
            $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'posthog']);
            $connection = app(WorkspaceContext::class)->run($workspace, fn () => Connection::query()->firstOrFail());
            $this->post('/w/setup/sources/setup', ['action' => 'account', 'connection_id' => $connection->id]);
        }
        $this->post('/w/setup/sources/setup', ['action' => 'resource', 'resource' => 0])->assertSessionHasNoErrors();
        $this->post('/w/setup/sources/setup', ['action' => 'finish', 'days' => 180, 'track' => 'event'])->assertSessionHasErrors('event');
        $this->post('/w/setup/sources/setup', ['action' => 'finish', 'days' => 180, 'track' => 'event', 'event' => 'user_signed_up'])->assertSessionHasErrors('resource');
        $this->post('/w/setup/sources/setup', ['action' => 'finish', 'days' => 180, 'track' => 'usage'])->assertSessionHasErrors('resource');
        app(WorkspaceContext::class)->run($workspace, function (): void {
            $sources = Source::query()->where('kind', 'posthog')->orderBy('id')->get();
            $this->assertSame([['project_id' => '48213', 'days' => 180], ['project_id' => '48213', 'event' => 'user_signed_up', 'days' => 180], ['project_id' => '48213', 'event' => 'invoice_sent', 'days' => 180]], $sources->pluck('config')->all());
            $this->assertSame(['PostHog · Synthetic app', 'PostHog · Synthetic app · user_signed_up', 'PostHog · Synthetic app · invoice_sent'], $sources->pluck('label')->all());
            $this->assertSame(['Europe/Berlin'], $sources->pluck('reporting_timezone')->unique()->values()->all());
            $this->assertSame(['base_url' => 'https://us.posthog.com', 'api_key' => 'phx_synthetic'], Connection::query()->firstOrFail()->reveal());
        });
        Queue::assertPushed(CollectSource::class, 3);
    }

    public function test_an_account_form_still_saves_after_another_page_closed_the_setup(): void
    {
        Http::preventStrayRequests();
        Http::fake(['us.posthog.com/api/projects/*' => Http::response(['results' => [['id' => 48213, 'name' => 'Synthetic app']], 'next' => null])]);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'posthog']);
        $page = $this->get('/w/setup/sources/new')->assertOk()->getContent();
        $this->assertStringContainsString('name="setup_project" value="'.$project->id.'"', $page);
        $this->assertStringContainsString('name="setup_kind" value="posthog"', $page);
        // Another tab opens a different page while the user creates the key, which ends the setup in the session.
        $this->get('/w/setup/projects/'.$project->id)->assertOk();
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'setup_project' => $project->id, 'setup_kind' => 'posthog',
            'values' => ['base_url' => 'https://us.posthog.com', 'api_key' => 'phx_synthetic']])->assertRedirect('/w/setup/sources/new')->assertSessionHasNoErrors();
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Synthetic app');
        $this->assertDatabaseHas('connections', ['kind' => 'posthog', 'workspace_id' => $workspace->id]);
    }

    public function test_a_setup_step_that_cannot_continue_starts_again_instead_of_failing(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        $archived = $this->projectIn($workspace, 'Archived');
        app(WorkspaceContext::class)->run($workspace, fn () => $archived->update(['archived' => true]));
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'wporg']);
        $this->get('/w/setup/projects/'.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'manual', 'setup_project' => $project->id, 'setup_kind' => 'wporg', 'resource_id' => 'synthetic-plugin', 'timezone' => 'UTC'])
            ->assertRedirect('/w/setup/sources/new?project='.$project->id)->assertSessionHas('status');
        $this->assertDatabaseCount('sources', 0);
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'setup_project' => $archived->id, 'setup_kind' => 'posthog', 'values' => ['base_url' => 'https://us.posthog.com', 'api_key' => 'phx_synthetic']])
            ->assertRedirect('/w/setup')->assertSessionHas('status');
        $this->post('/w/setup/sources/setup', ['action' => 'account', 'setup_project' => $project->id, 'setup_kind' => 'unknown', 'values' => []])->assertRedirect('/w/setup/sources/new?project='.$project->id);
        $this->assertDatabaseCount('connections', 0);
    }

    public function test_prefetches_and_subresource_requests_do_not_close_the_setup(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Setup', 'slug' => 'setup']);
        $project = $this->projectIn($workspace);
        $this->actingAs($owner)->get('/w/setup/sources/new?project='.$project->id);
        $this->post('/w/setup/sources/setup', ['action' => 'provider', 'kind' => 'wporg']);
        $this->get('/w/setup/projects/'.$project->id, ['Sec-Purpose' => 'prefetch;prerender'])->assertOk();
        $this->get('/w/setup/projects/'.$project->id, ['Purpose' => 'prefetch']);
        $this->get('/w/setup/projects/'.$project->id, ['Sec-Fetch-Mode' => 'cors', 'Sec-Fetch-Dest' => 'empty']);
        $this->get('/w/setup/sources/new')->assertOk()->assertSee('Search plugins');
        $this->get('/w/setup/projects/'.$project->id, ['Sec-Fetch-Mode' => 'navigate', 'Sec-Fetch-Dest' => 'document']);
        $this->get('/w/setup/sources/new')->assertRedirect('/w/setup');
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
