<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\Collector;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class CollectionPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-25T10:00:00Z'));
        $owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Pipeline', 'slug' => 'pipeline']);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        Http::preventStrayRequests();
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback(...));
    }

    private function source(): Source
    {
        $project = Project::create(['name' => 'Example', 'slug' => 'example']);

        return Source::create(['project_id' => $project->id, 'kind' => 'wporg', 'label' => 'Downloads', 'config' => ['slug' => 'example', 'limit' => 180], 'reporting_timezone' => 'UTC', 'identity_hash' => str_repeat('a', 64)])->fresh();
    }

    private function provider(?callable $onRequest = null): void
    {
        Http::fake(function (Request $request) use ($onRequest) {
            if ($onRequest) {
                $onRequest($request);
            }

            return str_contains($request->url(), '/info/') ? Http::response(['slug' => 'example']) : Http::response(['2026-10-24' => 12, '2026-10-25' => 3]);
        });
    }

    public function test_success_is_atomic_idempotent_and_resets_health(): void
    {
        $this->scoped(function () {
            $source = $this->source();
            $source->update(['consecutive_failures' => 2, 'error_code' => 'ConnectorException']);
            $this->provider();
            $first = app(Collector::class)->run($source);
            $second = app(Collector::class)->run($source);
            $this->assertSame('success', $first->status);
            $this->assertSame('success', $second->status);
            $this->assertSame(2, DailyMetric::count());
            $this->assertSame(2, $second->rows_written);
            $this->assertSame(0, $source->fresh()->consecutive_failures);
            $this->assertNull($source->fresh()->error_code);
            $this->assertSame('2026-10-25', $source->fresh()->latest_data_date);
            $this->assertSame('2026-10-24', $source->fresh()->finalized_through);
        });
    }

    public function test_provider_failure_keeps_history_and_stores_only_safe_category(): void
    {
        $this->scoped(function () {
            $source = $this->source();
            $fail = false;
            $this->provider(function () use (&$fail) {
                if ($fail) {
                    throw new \RuntimeException('fixture-token-must-never-be-stored');
                }
            });
            app(Collector::class)->run($source);
            $success = $source->fresh()->last_success_at;
            $fail = true;
            $failed = app(Collector::class)->run($source);
            $this->assertSame('failed', $failed->status);
            $this->assertSame('ConnectorException', $failed->error_code);
            $this->assertSame(2, DailyMetric::count());
            $this->assertTrue($success->equalTo($source->fresh()->last_success_at));
            $this->assertSame(1, $source->fresh()->consecutive_failures);
            $this->assertStringNotContainsString('fixture-token', json_encode(DB::table('sync_runs')->get(), JSON_THROW_ON_ERROR));
        });
    }

    public function test_changed_configuration_during_fetch_cancels_the_entire_result(): void
    {
        $this->scoped(function () {
            $source = $this->source();
            $changed = false;
            $this->provider(function () use ($source, &$changed) {
                if (! $changed) {
                    Source::query()->whereKey($source->id)->increment('version');
                    $changed = true;
                }
            });
            $run = app(Collector::class)->run($source);
            $this->assertSame('cancelled', $run->status);
            $this->assertSame(0, DailyMetric::count());
            $this->assertNull($source->fresh()->last_success_at);
        });
    }

    public function test_disabled_source_during_fetch_does_not_write(): void
    {
        $this->scoped(function () {
            $source = $this->source();
            $this->provider(fn () => Source::query()->whereKey($source->id)->update(['enabled' => false]));
            $this->assertSame('cancelled', app(Collector::class)->run($source)->status);
            $this->assertSame(0, DailyMetric::count());
        });
    }

    public function test_regular_sync_uses_revision_overlap_and_full_sync_uses_history_window(): void
    {
        $this->scoped(function () {
            $source = $this->source();
            $source->update(['last_success_at' => CarbonImmutable::now()]);
            $limits = [];
            $this->provider(function (Request $request) use (&$limits) {
                if (str_contains($request->url(), 'downloads.php')) {
                    $limits[] = $request['limit'];
                }
            });
            app(Collector::class)->run($source);
            app(Collector::class)->run($source, true);
            $this->assertSame([8, 180], $limits);
        });
    }

    public function test_rotated_credentials_during_fetch_cancel_old_results(): void
    {
        $this->scoped(function () {
            $project = Project::create(['name' => 'Example', 'slug' => 'example']);
            $connection = new Connection(['name' => 'Bing', 'kind' => 'bing', 'workspace_id' => $this->workspace->id]);
            $connection->storeSecrets(['api_key' => 'fixture-old-key']);
            $connection->save();
            $source = Source::create(['project_id' => $project->id, 'connection_id' => $connection->id, 'kind' => 'bing', 'label' => 'Bing', 'config' => ['site_url' => 'https://example.com'], 'identity_hash' => str_repeat('b', 64), 'reporting_timezone' => 'UTC']);
            $rotated = false;
            Http::fake(function (Request $request) use ($connection, &$rotated) {
                if (! $rotated) {
                    $connection->storeSecrets(['api_key' => 'fixture-new-key']);
                    $connection->version = 2;
                    $connection->save();
                    $rotated = true;
                }
                $row = ['Date' => '/Date(1792800000000)/'];
                $row += str_contains($request->url(), 'GetCrawlStats') ? ['InIndex' => 8] : ['Impressions' => 4, 'Clicks' => 1];

                return Http::response(['d' => [$row]]);
            });
            $this->assertSame('cancelled', app(Collector::class)->run($source)->status);
            $this->assertSame(0, DailyMetric::count());
        });
    }

    public function test_inline_sync_uses_the_same_overlap_middleware_as_workers(): void
    {
        $source = $this->scoped(fn () => $this->source());
        $this->provider();
        $job = new CollectSource($this->workspace->id, $source->id);
        $key = $job->middleware()[0]->getLockKey($job);
        $lock = Cache::lock($key, 60);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('metribay:sync', ['--workspace' => 'pipeline', '--inline' => true])->assertSuccessful();
        } finally {
            $lock->release();
        }
        $this->assertSame(0, DB::table('sync_runs')->count());
        Http::assertNothingSent();
        $this->artisan('metribay:sync', ['--workspace' => 'pipeline', '--inline' => true])->assertSuccessful();
        $this->assertSame('success', DB::table('sync_runs')->value('status'));
    }

    public function test_queue_failure_throws_a_safe_retryable_error_and_cleans_context(): void
    {
        $source = $this->scoped(fn () => $this->source());
        Http::fake(fn () => Http::response([], 503));
        try {
            dispatch_sync(new CollectSource($this->workspace->id, $source->id));
            $this->fail('Worker failures must retry.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('safe error category', $error->getMessage());
        }
        $this->assertSame('failed', DB::table('sync_runs')->value('status'));
        $this->expectException(\LogicException::class);
        app(WorkspaceContext::class)->current();
    }

    public function test_stale_loaded_model_cannot_be_deleted_in_a_different_workspace(): void
    {
        $project = $this->scoped(fn () => Project::create(['name' => 'Owned', 'slug' => 'owned']));
        $other = User::factory()->create();
        $foreign = Workspace::create(['owner_id' => $other->id, 'name' => 'Other', 'slug' => 'other']);
        $this->expectException(\LogicException::class);
        app(WorkspaceContext::class)->run($foreign, fn () => $project->delete());
    }
}
