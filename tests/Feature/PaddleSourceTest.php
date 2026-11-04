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
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\Collector;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class PaddleSourceTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'pdl_live_apikey_synthetic';

    private const ACCOUNT = 'slr_example';

    public function test_owner_connects_a_metrics_only_key_and_confirms_sharing_before_collection(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Studio', 'slug' => 'studio']);
        $scope = app(WorkspaceContext::class);
        $project = $scope->run($workspace, fn () => Project::create(['name' => 'Shop', 'slug' => 'shop']));
        $this->travelTo(CarbonImmutable::parse('2026-11-04T12:00:00Z'));
        Queue::fake();
        Http::preventStrayRequests();
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));

        $permissions = ['metrics.read', 'customer.read'];
        $account = self::ACCOUNT;
        Http::fake(function (Request $request) use (&$permissions, &$account) {
            $this->assertSame('GET', $request->method());
            $this->assertSame('api.paddle.com', parse_url($request->url(), PHP_URL_HOST));
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer '.self::KEY));

            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/') {
                return Http::response(['data' => ['caller' => ['auth_type' => 'api_key', 'permissions' => $permissions, 'account' => ['id' => $account]]]]);
            }

            $this->assertSame('2026-11-01', $request['from']);
            $this->assertSame('2026-11-04', $request['to']);
            $points = match ($path) {
                '/metrics/revenue' => [['amount' => '12550'], ['amount' => '0']],
                '/metrics/monthly-recurring-revenue' => [['amount' => '2900'], ['amount' => '5800']],
                '/metrics/active-subscribers' => [['count' => 1], ['count' => 2]],
                default => $this->fail('Unexpected Paddle endpoint'),
            };

            return Http::response(['data' => [
                'interval' => 'day', 'currency_code' => 'EUR', 'updated_at' => '2026-11-03T20:00:00Z',
                'timeseries' => [
                    ['timestamp' => '2026-11-01T00:00:00Z'] + $points[0],
                    ['timestamp' => '2026-11-03T00:00:00Z'] + $points[1],
                ],
            ]]);
        });

        $setup = '/w/studio/sources/setup';
        $this->actingAs($owner)->get('/w/studio/sources/new?project='.$project->id)->assertOk()->assertSee('Paddle Billing');
        $this->post($setup, ['action' => 'provider', 'kind' => 'paddle'])->assertSessionHasNoErrors();
        $this->post($setup, ['action' => 'account', 'values' => ['api_key' => self::KEY]])->assertSessionHasErrors('values.api_key');
        $this->assertDatabaseCount('connections', 0);
        $this->assertNull(session()->getOldInput('values'));

        $permissions = ['metrics.read'];
        $this->post($setup, ['action' => 'account', 'values' => ['api_key' => self::KEY]])->assertSessionHasNoErrors();
        $connection = $scope->run($workspace, fn () => Connection::query()->sole());
        $this->assertStringNotContainsString(self::KEY, $connection->getRawOriginal('encrypted_payload'));
        $this->get('/w/studio/sources/new')->assertOk()->assertSee('Whole Paddle account')->assertDontSee(self::KEY);
        $this->post($setup, ['action' => 'resource', 'resource' => 0])->assertSessionHasNoErrors();
        $this->get('/w/studio/sources/new')->assertOk()->assertSee('include them in workspace exports');
        $this->post($setup, ['action' => 'finish', 'days' => 3])->assertSessionHasErrors('share_revenue');
        $this->assertDatabaseCount('sources', 0);
        $this->post($setup, ['action' => 'finish', 'days' => 3, 'share_revenue' => '1'])->assertSessionHasNoErrors();
        $source = $scope->run($workspace, fn () => Source::query()->sole());
        Queue::assertPushed(CollectSource::class, fn ($job) => $job->sourceId === $source->id);

        $scope->run($workspace, function () use ($source, &$account): void {
            $collector = app(Collector::class);
            $this->assertSame('success', $collector->run($source)->status);
            $this->assertSame('success', $collector->run($source)->status);
            $this->assertSame(6, DailyMetric::query()->count());
            $metrics = collect(app(DashboardData::class)->projects(7)[0]['sources'][0]['metrics'])->keyBy('key');
            $this->assertSame(125.5, $metrics['paddle_revenue']['value']);
            $this->assertSame(58.0, $metrics['paddle_mrr']['value']);
            $this->assertSame(2.0, $metrics['paddle_active_subscribers']['value']);
            $this->assertSame(2, $metrics['paddle_revenue']['coverage']['known']);

            $account = 'slr_other';
            $this->assertSame('failed', $collector->run($source)->status);
            $this->assertSame(6, DailyMetric::query()->count());
        });

        $this->get('/w/studio/projects/'.$project->id)->assertOk()->assertSee('Paddle net revenue')->assertSee('€125.50');
        $this->assertStringContainsString('paddle_revenue,2026-11-01,2026-11-01,125.5,no,EUR', $this->get('/w/studio/export/history')->assertOk()->streamedContent());

        $scope->run($workspace, function (): void {
            DailyMetric::query()->where('metric', 'paddle_revenue')->whereDate('date', '2026-11-01')->sole()
                ->update(['context' => ['currency' => 'USD']]);
            $revenue = collect(app(DashboardData::class)->projects(7)[0]['sources'][0]['metrics'])->firstWhere('key', 'paddle_revenue');
            $this->assertNull($revenue['value']);
            $this->assertSame('EUR', $revenue['context']['currency']);
            $this->assertSame(['USD', 'EUR'], array_values(array_column(array_column($revenue['series'], 'context'), 'currency')));
        });
    }
}
