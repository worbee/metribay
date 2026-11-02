<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\MetricStore;
use Metribay\Core\Services\ProviderCatalog;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class GitHubDeploymentEventsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Deploys', 'slug' => 'deploys']);
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback(...));
    }

    private function connection(): Connection
    {
        return $this->scoped(function () {
            $connection = new Connection(['workspace_id' => app(WorkspaceContext::class)->id(), 'kind' => 'github', 'name' => 'GitHub account']);
            $connection->storeSecrets(['token' => 'synthetic-token']);
            $connection->save();

            return $connection;
        });
    }

    private function source(array $config = []): Source
    {
        $connection = $this->connection();

        return $this->scoped(function () use ($config, $connection) {
            $project = Project::create(['name' => 'Example', 'slug' => 'example']);
            $config += ['repository' => 'example/repo', 'days' => 180];

            return Source::create(['project_id' => $project->id, 'kind' => 'github', 'label' => 'GitHub · example/repo', 'connection_id' => $connection->id,
                'config' => $config, 'identity_hash' => ProviderCatalog::identityHash('github', $config, 'UTC'), 'reporting_timezone' => 'UTC'])->fresh();
        });
    }

    private function event(Source $source, string $origin, string $id, string $at): void
    {
        $this->scoped(fn () => ProjectEvent::create(['project_id' => $source->project_id, 'source_id' => $source->id, 'origin' => $origin, 'external_id' => $id,
            'date' => substr($at, 0, 10), 'occurred_at' => CarbonImmutable::parse($at), 'title' => $id, 'body' => '', 'url' => 'https://github.com/example/repo']));
    }

    private function fakeEnvironments(array $names): void
    {
        Http::preventStrayRequests();
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        Http::fake(function (Request $request) use ($names) {
            if (str_contains($request->url(), '/user/repos')) {
                return Http::response([]);
            }
            $this->assertStringContainsString('DeploymentEnvironments', $request['query']);
            $nodes = array_map(fn (string $name, int $index): array => ['id' => 'd'.$index, 'environment' => $name], $names, array_keys($names));

            return Http::response(['data' => ['repository' => ['deployments' => ['nodes' => $nodes, 'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]]]]]);
        });
    }

    public function test_only_the_read_window_of_each_event_origin_is_reconciled(): void
    {
        $source = $this->source();
        $this->event($source, 'github_release', 'old-release', '2026-01-10T10:00:00Z');
        $this->event($source, 'github_release', 'deleted-release', '2026-10-20T10:00:00Z');
        $this->event($source, 'github_deployment', 'old-deployment', '2026-10-01T10:00:00Z');
        $this->scoped(fn () => app(MetricStore::class)->persist($source, new CollectionResult(events: [
            ['origin' => 'github_release', 'external_id' => 'kept-release', 'title' => 'v2', 'url' => 'https://github.com/example/repo/releases/tag/v2', 'occurred_at' => '2026-10-24T10:00:00Z'],
            ['origin' => 'github_deployment', 'external_id' => 'new-deployment', 'title' => 'production · abc1234', 'url' => 'https://github.com/example/repo/commit/abc', 'occurred_at' => '2026-10-24T11:00:00Z'],
        ], authoritativeEvents: ['github_release' => '2026-10-01T00:00:00+00:00', 'github_deployment' => '2026-10-15T00:00:00+00:00']), CarbonImmutable::parse('2026-10-25T00:00:00Z')));
        $kept = $this->scoped(fn () => ProjectEvent::query()->orderBy('external_id')->pluck('origin', 'external_id')->all());
        $this->assertSame(['kept-release' => 'github_release', 'new-deployment' => 'github_deployment', 'old-deployment' => 'github_deployment', 'old-release' => 'github_release'], $kept);

        // An empty deployment filter reconciles all time and removes every deployment event.
        $this->scoped(fn () => app(MetricStore::class)->persist($source, new CollectionResult(authoritativeEvents: ['github_deployment' => null]), CarbonImmutable::now()));
        $this->assertSame(0, $this->scoped(fn () => ProjectEvent::query()->where('origin', 'github_deployment')->count()));
        $this->assertSame(2, $this->scoped(fn () => ProjectEvent::query()->where('origin', 'github_release')->count()));
    }

    public function test_events_with_an_origin_the_provider_does_not_import_are_rejected(): void
    {
        $source = $this->source();
        $this->expectException(\InvalidArgumentException::class);
        $this->scoped(fn () => app(MetricStore::class)->persist($source, new CollectionResult(events: [
            ['origin' => 'manual', 'external_id' => 'x', 'title' => 'x', 'url' => '', 'occurred_at' => '2026-10-24T10:00:00Z'],
        ]), CarbonImmutable::now()));
    }

    public function test_environment_names_are_trimmed_deduplicated_bounded_and_not_part_of_source_identity(): void
    {
        $this->assertSame(['production', 'Staging'], ProviderCatalog::environments(' production , Staging,PRODUCTION,, '));
        $this->assertSame([], ProviderCatalog::environments(''));
        $this->assertSame(ProviderCatalog::identityHash('github', ['repository' => 'a/b', 'days' => 30], 'UTC'),
            ProviderCatalog::identityHash('github', ['repository' => 'a/b', 'days' => 90, 'environments' => ['production']], 'UTC'));
        $this->expectException(ValidationException::class);
        ProviderCatalog::environments(implode(',', array_map(fn (int $n): string => 'env-'.$n, range(1, 11))));
    }

    public function test_setup_suggests_production_and_saves_chosen_environments(): void
    {
        Queue::fake();
        $this->fakeEnvironments(['staging', 'production']);
        $connection = $this->connection();
        $project = $this->scoped(fn () => Project::create(['name' => 'Example', 'slug' => 'example']));
        $this->actingAs($this->owner)->get('/w/deploys/sources/new?project='.$project->id)->assertOk();
        $this->post('/w/deploys/sources/setup', ['action' => 'provider', 'kind' => 'github']);
        $this->post('/w/deploys/sources/setup', ['action' => 'account', 'connection_id' => $connection->id])->assertSessionHasNoErrors();
        $this->post('/w/deploys/sources/setup', ['action' => 'manual', 'resource_id' => 'example/repo', 'timezone' => 'UTC'])->assertSessionHasNoErrors();
        $this->get('/w/deploys/sources/new')->assertOk()->assertSee('Deployment environments')
            ->assertSee('Recent deployments went to: production, staging.')->assertSee('value="production"', false);
        $this->post('/w/deploys/sources/setup', ['action' => 'finish', 'days' => 180, 'environments' => 'production, staging'])->assertSessionHasNoErrors();
        $source = $this->scoped(fn () => Source::query()->sole());
        $this->assertSame(['repository' => 'example/repo', 'days' => 180, 'environments' => ['production', 'staging']], $source->config);
    }

    public function test_setup_explains_missing_deployment_access_and_still_finishes(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        Http::fake(fn (Request $request) => str_contains($request->url(), '/user/repos') ? Http::response([])
            : Http::response(['errors' => [['type' => 'FORBIDDEN', 'message' => 'Resource not accessible by integration']], 'data' => ['repository' => ['deployments' => null]]]));
        $connection = $this->connection();
        $project = $this->scoped(fn () => Project::create(['name' => 'Example', 'slug' => 'example']));
        $this->actingAs($this->owner)->get('/w/deploys/sources/new?project='.$project->id);
        $this->post('/w/deploys/sources/setup', ['action' => 'provider', 'kind' => 'github']);
        $this->post('/w/deploys/sources/setup', ['action' => 'account', 'connection_id' => $connection->id]);
        $this->post('/w/deploys/sources/setup', ['action' => 'manual', 'resource_id' => 'example/repo', 'timezone' => 'UTC']);
        $this->get('/w/deploys/sources/new')->assertOk()->assertSee('GitHub did not allow reading deployments.');
        $this->post('/w/deploys/sources/setup', ['action' => 'finish', 'days' => 180])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('environments', $this->scoped(fn () => Source::query()->sole())->config);
    }

    public function test_changing_environments_removes_old_deployment_events_and_queues_an_import(): void
    {
        Queue::fake();
        $source = $this->source(['environments' => ['staging']]);
        $this->event($source, 'github_deployment', 'staging-deploy', '2026-10-20T10:00:00Z');
        $this->event($source, 'github_release', 'release', '2026-10-20T10:00:00Z');
        $hash = $source->identity_hash;
        $this->actingAs($this->owner)->patch('/w/deploys/sources/'.$source->id, ['version' => $source->version, 'label' => $source->label, 'days' => 180, 'connection_id' => $source->connection_id, 'environments' => 'production', 'enabled' => 1])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Data source updated. Deployment events are being imported again.');
        $source = $this->scoped(fn () => $source->fresh());
        $this->assertSame(['production'], $source->config['environments']);
        $this->assertSame($hash, $source->identity_hash);
        $this->assertSame(['release'], $this->scoped(fn () => ProjectEvent::query()->pluck('external_id')->all()));
        Queue::assertPushed(CollectSource::class, fn ($job) => $job->sourceId === $source->id && $job->full);

        // Saving the same environments keeps events and does not queue another import.
        Queue::fake();
        $this->patch('/w/deploys/sources/'.$source->id, ['version' => $source->version, 'label' => $source->label, 'days' => 180, 'connection_id' => $source->connection_id, 'environments' => 'production', 'enabled' => 1])
            ->assertSessionHas('status', 'Data source updated. Existing history is retained.');
        Queue::assertNothingPushed();

        $this->patch('/w/deploys/sources/'.$source->id, ['version' => $source->version + 1, 'label' => $source->label, 'days' => 180, 'connection_id' => $source->connection_id, 'environments' => '', 'enabled' => 1]);
        $this->assertArrayNotHasKey('environments', $this->scoped(fn () => $source->fresh())->config);
    }

    public function test_find_environments_shows_recent_environment_names_in_source_settings(): void
    {
        $this->fakeEnvironments(['production', 'preview-7']);
        $source = $this->source();
        $settings = '/w/deploys/projects/'.$source->project_id.'/settings';
        $this->actingAs($this->owner)->from($settings)->post('/w/deploys/sources/'.$source->id.'/environments')
            ->assertRedirect($settings.'#source-'.$source->id)->assertSessionHasNoErrors();
        $this->get($settings)->assertOk()->assertSee('Recent deployments went to: preview-7, production.')->assertSee('Find environments');
    }
}
