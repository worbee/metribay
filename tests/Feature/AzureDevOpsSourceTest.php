<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\ProviderCatalog;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class AzureDevOpsSourceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Azure', 'slug' => 'azure']);
        Http::preventStrayRequests();
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback(...));
    }

    /** @param int $environmentStatus HTTP status for environment requests. */
    private function fakeAzure(int $environmentStatus = 200): void
    {
        Http::fake(function (Request $request) use ($environmentStatus) {
            $this->assertSame('Basic '.base64_encode(':synthetic-pat'), $request->header('Authorization')[0]);
            $path = rawurldecode((string) parse_url($request->url(), PHP_URL_PATH));

            return match (true) {
                $path === '/fabrikam/_apis/git/repositories' => Http::response(['count' => 1, 'value' => [['id' => 'r1', 'name' => 'Web App', 'project' => ['name' => 'Shop']]]]),
                str_ends_with($path, '/distributedtask/environments') => $environmentStatus === 200
                    ? Http::response(['count' => 2, 'value' => [['id' => 1, 'name' => 'staging'], ['id' => 2, 'name' => 'production']]]) : Http::response('', $environmentStatus),
                default => throw new \RuntimeException('Unexpected request '.$request->url()),
            };
        });
    }

    private function source(array $config = []): Source
    {
        return $this->scoped(function () use ($config) {
            $connection = new Connection(['workspace_id' => app(WorkspaceContext::class)->id(), 'kind' => 'azure_devops', 'name' => 'Azure DevOps account']);
            $connection->storeSecrets(['organization' => 'fabrikam', 'token' => 'synthetic-pat']);
            $connection->save();
            $project = Project::create(['name' => 'Shop', 'slug' => 'shop']);
            $config += ['repository' => 'Shop/Web App', 'days' => 180];

            return Source::create(['project_id' => $project->id, 'kind' => 'azure_devops', 'label' => 'Azure DevOps · Shop/Web App', 'connection_id' => $connection->id,
                'config' => $config, 'identity_hash' => ProviderCatalog::identityHash('azure_devops', $config, 'UTC'), 'reporting_timezone' => 'UTC'])->fresh();
        });
    }

    public function test_setup_connects_an_organization_lists_repositories_and_saves_chosen_environments(): void
    {
        Queue::fake();
        $this->fakeAzure();
        $project = $this->scoped(fn () => Project::create(['name' => 'Shop', 'slug' => 'shop']));
        $this->actingAs($this->owner)->get('/w/azure/sources/new?project='.$project->id)->assertOk()->assertSee('Azure DevOps');
        $this->post('/w/azure/sources/setup', ['action' => 'provider', 'kind' => 'azure_devops'])->assertSessionHasNoErrors();
        $this->get('/w/azure/sources/new')->assertOk()->assertSee('Organization')->assertSee('dev.azure.com/organization')->assertSee('Environment (Read &amp; manage)', false);
        $this->post('/w/azure/sources/setup', ['action' => 'account', 'values' => ['organization' => 'https://dev.azure.com/fabrikam/', 'token' => 'synthetic-pat']])->assertSessionHasNoErrors();
        $connection = $this->scoped(fn () => Connection::query()->sole());
        $this->assertSame(['organization' => 'fabrikam', 'token' => 'synthetic-pat'], $connection->reveal());
        $this->get('/w/azure/sources/new')->assertOk()->assertSee('Shop/Web App');
        $this->post('/w/azure/sources/setup', ['action' => 'resource', 'resource' => 0])->assertSessionHasNoErrors();
        $this->get('/w/azure/sources/new')->assertOk()->assertSee('Successful Azure DevOps deployments')
            ->assertSee('This project’s environments: production, staging.')->assertSee('value="production"', false);
        $this->post('/w/azure/sources/setup', ['action' => 'finish', 'days' => 90, 'environments' => 'production'])->assertSessionHasNoErrors();
        $source = $this->scoped(fn () => Source::query()->sole());
        $this->assertSame(['repository' => 'Shop/Web App', 'days' => 90, 'environments' => ['production']], $source->config);
        $this->assertSame('UTC', $source->reporting_timezone);
        Queue::assertPushed(CollectSource::class, fn ($job) => $job->sourceId === $source->id && $job->full);

        // The same repository cannot be added twice, whatever its letter case.
        $this->get('/w/azure/sources/new?project='.$project->id);
        $this->post('/w/azure/sources/setup', ['action' => 'provider', 'kind' => 'azure_devops']);
        $this->post('/w/azure/sources/setup', ['action' => 'account', 'connection_id' => $connection->id]);
        $this->post('/w/azure/sources/setup', ['action' => 'manual', 'resource_id' => 'shop/web app', 'timezone' => 'UTC'])->assertSessionHasErrors('resource');
    }

    public function test_setup_explains_a_token_without_environment_scope_and_still_finishes(): void
    {
        Queue::fake();
        $this->fakeAzure(environmentStatus: 401);
        $source = $this->source();
        $connection = $this->scoped(fn () => $source->connection);
        $project = $this->scoped(fn () => Project::create(['name' => 'Other', 'slug' => 'other']));
        $this->actingAs($this->owner)->get('/w/azure/sources/new?project='.$project->id);
        $this->post('/w/azure/sources/setup', ['action' => 'provider', 'kind' => 'azure_devops']);
        $this->post('/w/azure/sources/setup', ['action' => 'account', 'connection_id' => $connection->id]);
        $this->post('/w/azure/sources/setup', ['action' => 'manual', 'resource_id' => 'Shop/Web App', 'timezone' => 'UTC'])->assertSessionHasNoErrors();
        $this->get('/w/azure/sources/new')->assertOk()->assertSee('Azure DevOps did not allow reading environments.');
        $this->post('/w/azure/sources/setup', ['action' => 'finish', 'days' => 180])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('environments', $this->scoped(fn () => Source::query()->where('project_id', $project->id)->sole())->config);
    }

    public function test_changing_environments_removes_old_deployment_events_and_find_environments_lists_them(): void
    {
        Queue::fake();
        $this->fakeAzure();
        $source = $this->source(['environments' => ['staging']]);
        $this->scoped(fn () => ProjectEvent::create(['project_id' => $source->project_id, 'source_id' => $source->id, 'origin' => 'azure_devops_deployment', 'external_id' => 'run:1:staging',
            'date' => '2026-10-20', 'occurred_at' => CarbonImmutable::parse('2026-10-20T10:00:00Z'), 'title' => 'staging · abc1234', 'body' => '', 'url' => 'https://dev.azure.com/fabrikam/Shop/_build/results?buildId=1']));
        $settings = '/w/azure/projects/'.$source->project_id.'/settings';
        $this->actingAs($this->owner)->get($settings)->assertOk()->assertSee('Find environments');
        $this->from($settings)->post('/w/azure/sources/'.$source->id.'/environments')->assertRedirect($settings.'#source-'.$source->id)->assertSessionHasNoErrors();
        $this->get($settings)->assertOk()->assertSee('This project’s environments: production, staging.');
        $this->patch('/w/azure/sources/'.$source->id, ['version' => $source->version, 'label' => $source->label, 'days' => 180, 'connection_id' => $source->connection_id, 'environments' => 'production', 'enabled' => 1])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Data source updated. Deployment events are being imported again.');
        $this->assertSame(['production'], $this->scoped(fn () => $source->fresh())->config['environments']);
        $this->assertSame(0, $this->scoped(fn () => ProjectEvent::query()->count()));
        Queue::assertPushed(CollectSource::class, fn ($job) => $job->sourceId === $source->id && $job->full);
    }

    public function test_project_page_charts_pipeline_runs_and_labels_imported_deployments(): void
    {
        $source = $this->source();
        $today = CarbonImmutable::now('UTC')->toDateString();
        $this->scoped(function () use ($source, $today): void {
            $source->forceFill(['last_success_at' => now()])->save();
            foreach (['commits' => 4, 'prs_merged' => 1, 'pipeline_runs_succeeded' => 3, 'pipeline_runs_failed' => 1, 'open_issues' => 5] as $metric => $value) {
                DailyMetric::create(['source_id' => $source->id, 'metric' => $metric, 'date' => $today, 'value' => $value, 'is_partial' => false, 'collected_at' => now(), 'observed_at' => now()]);
            }
            ProjectEvent::create(['project_id' => $source->project_id, 'source_id' => $source->id, 'origin' => 'azure_devops_deployment', 'external_id' => 'run:7:production',
                'date' => $today, 'occurred_at' => CarbonImmutable::now('UTC'), 'title' => 'production · v1.2.0', 'body' => '', 'url' => 'https://dev.azure.com/fabrikam/Shop/_build/results?buildId=7']);
        });
        $this->actingAs($this->owner)->get('/w/azure/projects/'.$source->project_id)->assertOk()
            ->assertSee('Pipeline runs succeeded')->assertSee('Pipeline runs failed')->assertSee('production · v1.2.0')->assertSee('Azure DevOps');
    }
}
