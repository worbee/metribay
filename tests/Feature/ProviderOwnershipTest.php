<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Contracts\ConnectionCredentials;
use Metribay\Core\Contracts\ProviderRequests;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\ProviderCatalog;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ProviderOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_rejects_managed_references_but_accepts_own_credentials(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Own app', 'slug' => 'own']);
        app(WorkspaceContext::class)->run($workspace, function (): void {
            $connection = new Connection(['kind' => 'github', 'name' => 'Own token', 'workspace_id' => app(WorkspaceContext::class)->id()]);
            $connection->storeSecrets(['token' => 'synthetic-token']);
            $this->assertSame(['token' => 'synthetic-token'], app(ConnectionCredentials::class)->resolve($connection));
            $connection->storeSecrets(['managed_app' => 'google', 'client_id' => 'fixture-id', 'refresh_token' => 'fixture-refresh']);
            $this->expectException(ConnectorException::class);
            app(ConnectionCredentials::class)->resolve($connection);
        });
    }

    public function test_managed_reference_cannot_be_submitted_as_user_credentials(): void
    {
        $this->expectException(ValidationException::class);
        ProviderCatalog::credentials('ga4', ['managed_app' => 'google', 'refresh_token' => 'synthetic']);
    }

    public function test_core_ui_explains_own_credentials_and_never_uses_a_cloud_app(): void
    {
        $owner = User::factory()->create();
        Workspace::create(['owner_id' => $owner->id, 'name' => 'Own app', 'slug' => 'own']);
        config(['metribay.google.client_id' => null, 'metribay.google.client_secret' => null]);
        $this->actingAs($owner)->post('/w/own/sources/setup', ['action' => 'provider', 'kind' => 'ga4']);
        $this->get('/w/own/sources/new')->assertOk()->assertSee('Use your own credentials')->assertSee('Explore Cloud')->assertDontSee('/w/own/sources/google', false);
        $this->get('/w/own/sources/google')->assertUnprocessable();
    }

    public function test_host_policy_stops_http_before_any_request(): void
    {
        Http::fake();
        $this->app->instance(ProviderRequests::class, new class implements ProviderRequests
        {
            public function authorize(): void
            {
                throw new ConnectorException('Budget reached');
            }
        });
        try {
            (new SafeHttpClient(fn () => ['93.184.216.34']))->request('GET', 'https://example.com');
            $this->fail('Expected host denial');
        } catch (ConnectorException $exception) {
            $this->assertSame('Budget reached', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_google_app_change_invalidates_pending_authorization(): void
    {
        Http::fake();
        $owner = User::factory()->create();
        Workspace::create(['owner_id' => $owner->id, 'name' => 'Own app', 'slug' => 'own']);
        config(['metribay.google.client_id' => 'original-app', 'metribay.google.client_secret' => 'fixture-secret']);
        $this->actingAs($owner)->post('/w/own/sources/setup', ['action' => 'provider', 'kind' => 'ga4']);
        $this->get('/w/own/sources/google')->assertRedirect();
        $state = session('metribay_google.state');
        config(['metribay.google.client_id' => 'changed-app']);
        $this->get('/integrations/google/callback?state='.$state.'&code=fixture')->assertConflict();
        Http::assertNothingSent();
    }
}
