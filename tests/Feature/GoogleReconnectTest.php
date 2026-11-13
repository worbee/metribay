<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Http\Controllers\SourceSetupController;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class GoogleReconnectTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        config(['metribay.google.client_id' => 'fixture-id', 'metribay.google.client_secret' => 'fixture-secret']);
        Http::preventStrayRequests();
        $this->fakeGoogle();
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Reconnect', 'slug' => 'reconnect']);
        $this->project = $this->scoped(fn () => Project::create(['name' => 'Example', 'slug' => 'example']));
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback);
    }

    /** @param array<string, mixed> $secrets */
    private function connection(string $kind, array $secrets = ['client_id' => 'fixture-id', 'client_secret' => 'fixture-secret', 'refresh_token' => 'old-refresh']): Connection
    {
        return $this->scoped(function () use ($kind, $secrets) {
            $connection = new Connection(['workspace_id' => $this->workspace->id, 'kind' => $kind, 'name' => strtoupper($kind).' · fixture']);
            $connection->storeSecrets($secrets);
            $connection->save();

            return $connection;
        });
    }

    /** @param array<string, mixed> $config */
    private function source(Connection $connection, array $config, bool $enabled = true): Source
    {
        return $this->scoped(fn () => Source::create(['project_id' => $this->project->id, 'connection_id' => $connection->id, 'kind' => $connection->kind, 'label' => 'Fixture '.json_encode($config), 'config' => $config, 'identity_hash' => hash('sha256', json_encode($config)), 'reporting_timezone' => $connection->kind === 'gsc' ? 'America/Los_Angeles' : 'UTC', 'enabled' => $enabled]));
    }

    /** @var list<string> Search Console sites the new sign-in can list. */
    private array $sites = [];

    /** @var list<string> GA4 properties the new sign-in can read. */
    private array $properties = [];

    /** @var list<string> Tokens sent to Google's revocation endpoint. */
    private array $revoked = [];

    private mixed $revokeResponse = null;

    private function fakeGoogle(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return $request['grant_type'] === 'authorization_code'
                    ? Http::response(['refresh_token' => 'new-refresh'])
                    : Http::response(['access_token' => 'synthetic-access', 'expires_in' => 3600]);
            }
            if ($request->url() === 'https://oauth2.googleapis.com/revoke') {
                $this->revoked[] = $request['token'];

                return $this->revokeResponse ?? Http::response([]);
            }
            if ($request->url() === 'https://www.googleapis.com/webmasters/v3/sites') {
                return Http::response(['siteEntry' => array_map(fn (string $site) => ['siteUrl' => $site, 'permissionLevel' => 'siteOwner'], $this->sites)]);
            }
            if (preg_match('~^https://analyticsadmin\.googleapis\.com/v1beta/properties/(\d+)$~', $request->url(), $match)) {
                return in_array($match[1], $this->properties, true) ? Http::response(['timeZone' => 'Europe/Sofia']) : Http::response(['error' => ['code' => 403]], 403);
            }

            return Http::response([], 404);
        });
    }

    private function reconnect(Connection $connection): TestResponse
    {
        $this->actingAs($this->owner)->get('/w/reconnect/connections/'.$connection->id.'/google')
            ->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth?')
            ->assertRedirectContains(urlencode($connection->kind === 'gsc' ? 'https://www.googleapis.com/auth/webmasters.readonly' : 'https://www.googleapis.com/auth/analytics.readonly'));

        return $this->get('/integrations/google/callback?state='.session('metribay_google.state').'&code=fixture-code');
    }

    public function test_reconnect_renews_the_same_account_and_restarts_its_data_sources(): void
    {
        Queue::fake();
        $connection = $this->connection('gsc');
        $first = $this->source($connection, ['site_url' => 'https://example.test/']);
        $second = $this->source($connection, ['site_url' => 'sc-domain:example.test']);
        $this->source($connection, ['site_url' => 'https://paused.example.test/'], false);
        $this->sites = ['https://example.test/', 'sc-domain:example.test', 'https://paused.example.test/'];

        $this->reconnect($connection)
            ->assertRedirect('/w/reconnect/settings?tab=accounts#connection-'.$connection->id)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Google account reconnected. Collection restarted for 2 data sources.');

        $this->assertDatabaseCount('connections', 1);
        $this->scoped(function () use ($connection) {
            $fresh = Connection::findOrFail($connection->id);
            $this->assertSame('new-refresh', $fresh->reveal()['refresh_token']);
            $this->assertSame(2, $fresh->version);
            $this->assertSame(3, Source::query()->where('connection_id', $connection->id)->count());
        });
        Queue::assertPushed(CollectSource::class, 2);
        Queue::assertPushed(CollectSource::class, fn (CollectSource $job) => $job->sourceId === $first->id);
        Queue::assertPushed(CollectSource::class, fn (CollectSource $job) => $job->sourceId === $second->id);
        $this->get('/integrations/google/callback?state=replayed&code=fixture-code')->assertNotFound();
    }

    public function test_reconnect_keeps_the_saved_access_when_the_new_sign_in_cannot_read_a_site(): void
    {
        Queue::fake();
        $this->sites = ['https://example.test/'];
        $connection = $this->connection('gsc');
        $this->source($connection, ['site_url' => 'https://example.test/']);
        $this->source($connection, ['site_url' => 'sc-domain:other.example.test']);

        $this->reconnect($connection)
            ->assertRedirect('/w/reconnect/settings?tab=accounts#connection-'.$connection->id)
            ->assertSessionHasErrors(['account' => 'This Google account cannot read sc-domain:other.example.test. Sign in with the Google account that has access. The saved access was not changed.']);

        $this->scoped(function () use ($connection) {
            $fresh = Connection::findOrFail($connection->id);
            $this->assertSame('old-refresh', $fresh->reveal()['refresh_token']);
            $this->assertSame(1, $fresh->version);
        });
        Queue::assertNothingPushed();
    }

    public function test_ga4_reconnect_checks_each_property(): void
    {
        Queue::fake();
        $this->properties = ['111'];
        $connection = $this->connection('ga4');
        $this->source($connection, ['property_id' => '111']);
        $this->source($connection, ['property_id' => '222']);

        $this->reconnect($connection)->assertSessionHasErrors(['account' => 'This Google account cannot read GA4 property 222. Sign in with the Google account that has access. The saved access was not changed.']);
        $this->scoped(fn () => $this->assertSame('old-refresh', Connection::findOrFail($connection->id)->reveal()['refresh_token']));

        $this->properties = ['111', '222'];
        $this->reconnect($connection)->assertSessionHasNoErrors()->assertSessionHas('status', 'Google account reconnected. Collection restarted for 2 data sources.');
        $this->scoped(fn () => $this->assertSame('new-refresh', Connection::findOrFail($connection->id)->reveal()['refresh_token']));
        Queue::assertPushed(CollectSource::class, 2);
    }

    public function test_browser_connected_accounts_are_named_after_the_google_product(): void
    {
        $this->assertMatchesRegularExpression('/^GSC · \d{2} \w{3} \d{2}:\d{2}:\d{2}$/u', SourceSetupController::googleAccountName('gsc'));
        $this->assertMatchesRegularExpression('/^GA4 · \d{2} \w{3} \d{2}:\d{2}:\d{2}$/u', SourceSetupController::googleAccountName('ga4'));
    }

    public function test_declined_consent_returns_to_the_account_without_changes(): void
    {
        $connection = $this->connection('gsc');
        $this->actingAs($this->owner)->get('/w/reconnect/connections/'.$connection->id.'/google');
        $this->get('/integrations/google/callback?state='.session('metribay_google.state').'&error=access_denied')
            ->assertRedirect('/w/reconnect/settings?tab=accounts#connection-'.$connection->id)
            ->assertSessionHasErrors(['account' => 'Google access was not granted. Try again or use another account.']);
        $this->scoped(fn () => $this->assertSame('old-refresh', Connection::findOrFail($connection->id)->reveal()['refresh_token']));
    }

    public function test_reconnect_is_only_for_google_accounts_and_account_managers(): void
    {
        $github = $this->connection('github', ['token' => 'synthetic-token']);
        $this->actingAs($this->owner)->get('/w/reconnect/connections/'.$github->id.'/google')->assertNotFound();

        $this->app->instance(WorkspaceAccess::class, new class implements WorkspaceAccess
        {
            public function queryFor(Authenticatable $user): Builder
            {
                return Workspace::query();
            }

            public function allows(Authenticatable $user, Workspace $workspace, string $ability): bool
            {
                return $ability !== 'manage_connections';
            }
        });
        $google = $this->connection('gsc');
        $this->get('/w/reconnect/connections/'.$google->id.'/google')->assertForbidden();
        $this->get('/w/reconnect/settings?tab=accounts')->assertOk()->assertDontSee('Reconnect Google');
    }

    public function test_accounts_page_offers_reconnect_and_hides_credential_fields_for_host_managed_accounts(): void
    {
        $own = $this->connection('gsc');
        $managed = $this->connection('ga4', ['managed_app' => 'google', 'client_id' => 'fixture-id', 'refresh_token' => 'synthetic']);
        $page = $this->actingAs($this->owner)->get('/w/reconnect/settings?tab=accounts')->assertOk()
            ->assertSee('href="'.route('metribay.connections.google', ['workspace' => 'reconnect', 'connection' => $own->id]).'"', false)
            ->assertSee('href="'.route('metribay.connections.google', ['workspace' => 'reconnect', 'connection' => $managed->id]).'"', false)
            ->assertSee('Reconnect Google')
            ->assertSee('This account was connected by signing in with Google. To renew its access, use Reconnect Google.');
        $html = $page->getContent();
        $this->assertStringContainsString('connection-'.$own->id.'-service_account', $html);
        $this->assertStringNotContainsString('connection-'.$managed->id.'-service_account', $html);

        config(['metribay.google.client_id' => null]);
        $this->get('/w/reconnect/settings?tab=accounts')->assertOk()->assertDontSee('Reconnect Google');
    }

    public function test_removing_a_google_account_can_revoke_its_access_at_google(): void
    {
        $connection = $this->connection('gsc');
        $source = $this->source($connection, ['site_url' => 'https://example.test/']);
        $this->actingAs($this->owner)->get('/w/reconnect/settings?tab=accounts')->assertOk()->assertSee('Also revoke Metribay’s access at Google')->assertSee('Removing this account pauses 1 data source.');

        $this->delete('/w/reconnect/connections/'.$connection->id, ['revoke' => '1'])->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Connected account removed. Its saved credentials were deleted. 1 data source is paused until you choose another account; its history stays. Metribay’s access was revoked at Google.');
        $this->assertSame(['old-refresh'], $this->revoked);
        $this->assertDatabaseMissing('connections', ['id' => $connection->id]);
        $this->assertDatabaseHas('sources', ['id' => $source->id, 'connection_id' => null, 'enabled' => false]);
    }

    public function test_google_access_is_left_alone_unless_revocation_is_chosen(): void
    {
        $connection = $this->connection('ga4');
        $this->actingAs($this->owner)->delete('/w/reconnect/connections/'.$connection->id, ['revoke' => '0'])
            ->assertSessionHas('status', 'Connected account removed. Its saved credentials were deleted.');
        $this->assertSame([], $this->revoked);
        $this->assertDatabaseMissing('connections', ['id' => $connection->id]);
    }

    public function test_a_failed_revocation_still_removes_the_account_and_explains_the_next_step(): void
    {
        $this->revokeResponse = Http::response(['error' => 'server_error'], 503);
        $connection = $this->connection('gsc');
        $this->actingAs($this->owner)->delete('/w/reconnect/connections/'.$connection->id, ['revoke' => '1'])
            ->assertSessionHas('status', 'Connected account removed. Its saved credentials were deleted. Google did not confirm the revocation. Remove Metribay under Third-party connections in your Google Account.');
        $this->assertSame(['old-refresh'], $this->revoked);
        $this->assertDatabaseMissing('connections', ['id' => $connection->id]);

        $this->revokeResponse = Http::response(['error' => 'invalid_token'], 400);
        $expired = $this->connection('ga4');
        $this->delete('/w/reconnect/connections/'.$expired->id, ['revoke' => '1'])->assertSessionHas('status', 'Connected account removed. Its saved credentials were deleted. Metribay’s access was revoked at Google.');
    }

    public function test_service_account_keys_offer_no_google_revocation(): void
    {
        $key = $this->connection('ga4', ['service_account' => ['client_email' => 'fixture@example.iam.gserviceaccount.com', 'private_key' => 'fixture-key']]);
        $this->actingAs($this->owner)->get('/w/reconnect/settings?tab=accounts')->assertOk()->assertDontSee('Also revoke Metribay’s access at Google');
        $this->delete('/w/reconnect/connections/'.$key->id, ['revoke' => '1'])->assertSessionHas('status', 'Connected account removed. Its saved credentials were deleted.');
        $this->assertSame([], $this->revoked);
    }
}
