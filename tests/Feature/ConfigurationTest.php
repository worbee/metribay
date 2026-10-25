<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\ConfigurationTransfer;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Config', 'slug' => 'config']);
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback(...));
    }

    public function test_credentials_are_never_flashed_back_on_validation_errors(): void
    {
        $this->actingAs($this->owner)->post('/w/config/connections', ['name' => '', 'kind' => 'bing', 'values' => ['api_key' => 'fixture-secret-must-not-be-flashed']])->assertRedirect()->assertSessionHasErrors('name')->assertSessionMissing('_old_input.values');
    }

    public function test_empty_export_is_a_valid_import(): void
    {
        $this->scoped(function () {
            $transfer = app(ConfigurationTransfer::class);
            $transfer->import($transfer->export());
            $this->assertSame(0, Project::count());
        });
    }

    public function test_import_normalizes_domains_and_is_atomic_when_a_later_source_is_invalid(): void
    {
        $this->scoped(function () {
            $transfer = app(ConfigurationTransfer::class);
            $document = ['format' => 'metribay', 'version' => 1, 'projects' => [['slug' => 'example', 'name' => 'Example', 'domain' => 'https://EXAMPLE.com/', 'archived' => false, 'sources' => []]]];
            $transfer->import($document);
            $this->assertSame('example.com', Project::first()->domain);
            $document['projects'][0]['name'] = 'Must roll back';
            $document['projects'][0]['sources'] = [['kind' => 'wporg', 'label' => 'Invalid', 'config' => ['slug' => 'bad-slug-'], 'reporting_timezone' => 'UTC', 'enabled' => true]];
            try {
                $transfer->import($document);
                $this->fail('Invalid imports must fail.');
            } catch (ValidationException) {
            }
            $this->assertSame('Example', Project::first()->name);
            $this->assertSame(0, DB::table('sources')->count());
        });
    }

    public function test_import_pauses_sources_when_matching_credentials_do_not_exist(): void
    {
        $this->scoped(function () {
            app(ConfigurationTransfer::class)->import(['format' => 'metribay', 'version' => 1, 'projects' => [['slug' => 'example', 'name' => 'Example', 'archived' => false, 'sources' => [['kind' => 'bing', 'label' => 'Bing', 'config' => ['site_url' => 'https://example.com'], 'reporting_timezone' => 'UTC', 'enabled' => true, 'connection' => 'Missing']]]]]);
            $this->assertFalse((bool) DB::table('sources')->value('enabled'));
        });
    }

    public function test_browser_numeric_fields_are_normalized_before_collection(): void
    {
        $project = $this->scoped(fn () => Project::create(['name' => 'Example', 'slug' => 'example']));
        $this->actingAs($this->owner)->post('/w/config/sources', ['project_id' => (string) $project->id, 'kind' => 'wporg', 'label' => 'Downloads', 'reporting_timezone' => 'UTC', 'config' => ['slug' => 'example', 'limit' => '14']])->assertRedirect()->assertSessionHasNoErrors();
        $source = $this->scoped(fn () => Source::first());
        $this->assertSame(14, $source->config['limit']);
        $this->patch('/w/config/sources/'.$source->id, ['label' => 'Downloads', 'days' => '30', 'version' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(30, $this->scoped(fn () => $source->fresh()->config['limit']));
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        Http::preventStrayRequests();
        Http::fake(fn ($request) => Http::response(str_contains($request->url(), '/info/') ? ['slug' => 'example'] : [now()->subDay()->toDateString() => 5]));
        $this->artisan('metribay:sync', ['--workspace' => 'config', '--inline' => true])->assertSuccessful();
        $this->assertSame('success', DB::table('sync_runs')->value('status'));
    }

    public function test_reassigning_a_source_connection_preserves_its_identity_and_history(): void
    {
        $fixture = $this->scoped(function () {
            $project = Project::create(['name' => 'Example', 'slug' => 'example']);
            $connections = [];
            foreach (['First', 'Replacement'] as $name) {
                $connection = new Connection(['name' => $name, 'kind' => 'bing', 'workspace_id' => $this->workspace->id]);
                $connection->storeSecrets(['api_key' => 'synthetic-key-only']);
                $connection->save();
                $connections[] = $connection;
            }
            $source = Source::create(['project_id' => $project->id, 'connection_id' => $connections[0]->id, 'kind' => 'bing', 'label' => 'Search', 'config' => ['site_url' => 'https://example.com'], 'reporting_timezone' => 'UTC', 'identity_hash' => str_repeat('b', 64)])->fresh();
            DailyMetric::create(['source_id' => $source->id, 'date' => '2026-10-24', 'metric' => 'impressions', 'value' => 12, 'collected_at' => now()]);

            return [$source, $connections[1]];
        });
        [$source,$replacement] = $fixture;
        $this->actingAs($this->owner)->patch('/w/config/sources/'.$source->id, ['label' => 'Search', 'version' => 1, 'connection_id' => $replacement->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->scoped(function () use ($source, $replacement) {
            $fresh = $source->fresh();
            $this->assertSame($replacement->id, $fresh->connection_id);
            $this->assertSame(str_repeat('b', 64), $fresh->identity_hash);
            $this->assertSame(1, DailyMetric::count());
        });
        $other = User::factory()->create();
        $foreign = Workspace::create(['owner_id' => $other->id, 'name' => 'Foreign', 'slug' => 'foreign']);
        $connection = app(WorkspaceContext::class)->run($foreign, function () use ($foreign) {
            $c = new Connection(['name' => 'Foreign', 'kind' => 'bing', 'workspace_id' => $foreign->id]);
            $c->storeSecrets(['api_key' => 'synthetic-key-only']);
            $c->save();

            return $c;
        });
        $this->patchJson('/w/config/sources/'.$source->id, ['label' => 'Search', 'version' => 2, 'connection_id' => $connection->id])->assertUnprocessable()->assertJsonValidationErrors('connection_id');
    }
}
