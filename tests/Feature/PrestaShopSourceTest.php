<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\Collector;
use Metribay\Core\Services\ConfigurationTransfer;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\PersonalWorkspaceAccess;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class PrestaShopSourceTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SYNTHETICPRESTASHOPWEBSERVICE001';

    public function test_owner_adds_a_listed_shop_with_its_timezone_and_confirms_sharing(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Studio', 'slug' => 'studio']);
        $scope = app(WorkspaceContext::class);
        $project = $scope->run($workspace, fn () => Project::create(['name' => 'Prints', 'slug' => 'prints']));
        $this->travelTo(CarbonImmutable::parse('2026-11-04T12:00:00Z'));
        Queue::fake();
        Http::preventStrayRequests();
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        Http::fake(function (Request $request) {
            $this->assertTrue($request->hasHeader('Authorization', 'Basic '.base64_encode(self::KEY.':')));

            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/api/shops' => Http::response(['shops' => [['id' => 1, 'name' => 'Synthetic prints', 'active' => '1']]]),
                '/api/currencies' => Http::response(['currencies' => [['id' => 1, 'iso_code' => 'EUR', 'conversion_rate' => '1.000000', 'active' => '1', 'deleted' => '0']]]),
                '/api/orders' => Http::response(['orders' => [
                    // 23:30 local time on 1 November is still 1 November in the shop's timezone.
                    ['id' => 1, 'date_add' => '2026-11-01 23:30:00', 'total_paid_tax_excl' => '80.000000', 'conversion_rate' => '1.000000'],
                    ['id' => 2, 'date_add' => '2026-11-03 10:00:00', 'total_paid_tax_excl' => '45.500000', 'conversion_rate' => '1.000000'],
                ]]),
                default => $this->fail('Unexpected PrestaShop resource'),
            };
        });

        $setup = '/w/studio/sources/setup';
        $this->actingAs($owner)->get('/w/studio/sources/new?project='.$project->id)->assertOk()->assertSeeInOrder(['Revenue', 'Paddle Billing', 'PrestaShop']);
        $this->post($setup, ['action' => 'provider', 'kind' => 'prestashop'])->assertSessionHasNoErrors();
        $this->get('/w/studio/sources/new')->assertOk()->assertSee('Shop URL')->assertSee('Webservice key')->assertSee('orders, currencies and shops');
        $this->post($setup, ['action' => 'account', 'values' => ['base_url' => 'https://shop.example.com', 'api_key' => 'too-short']])->assertSessionHasErrors('values.api_key');
        $this->assertDatabaseCount('connections', 0);
        $this->post($setup, ['action' => 'account', 'values' => ['base_url' => 'https://shop.example.com/api/', 'api_key' => self::KEY]])->assertSessionHasNoErrors();
        $connection = $scope->run($workspace, fn () => Connection::query()->sole());
        $this->assertStringNotContainsString(self::KEY, $connection->getRawOriginal('encrypted_payload'));
        $this->get('/w/studio/sources/new')->assertOk()->assertSee('Synthetic prints')->assertDontSee(self::KEY);

        $this->post($setup, ['action' => 'resource', 'resource' => 0])->assertSessionHasNoErrors();
        $this->get('/w/studio/sources/new')->assertOk()->assertSee('Shop timezone')->assertSee('Share this shop’s orders and revenue');
        $this->post($setup, ['action' => 'finish', 'days' => 4, 'timezone' => 'Europe/Vienna'])->assertSessionHasErrors('share_revenue');
        $this->post($setup, ['action' => 'finish', 'days' => 4, 'share_revenue' => '1', 'timezone' => 'Not/AZone'])->assertSessionHasErrors('timezone');
        $this->assertDatabaseCount('sources', 0);
        $this->post($setup, ['action' => 'finish', 'days' => 4, 'share_revenue' => '1', 'timezone' => 'Europe/Vienna'])->assertSessionHasNoErrors();
        $source = $scope->run($workspace, fn () => Source::query()->sole());
        $this->assertSame(['shop_id' => '1', 'days' => 4], $source->config);
        $this->assertSame('Europe/Vienna', $source->reporting_timezone);
        Queue::assertPushed(CollectSource::class, fn ($job) => $job->sourceId === $source->id);

        $scope->run($workspace, function () use ($source): void {
            $this->assertSame('success', app(Collector::class)->run($source)->status);
            $this->assertSame(8, DailyMetric::query()->count());
            $metrics = collect(app(DashboardData::class)->projects(7)[0]['sources'][0]['metrics'])->keyBy('key');
            $this->assertSame(125.5, $metrics['store_revenue']['value']);
            $this->assertSame('EUR', $metrics['store_revenue']['context']['currency']);
            $this->assertSame(2.0, $metrics['store_orders']['value']);
        });
        $this->get('/w/studio/projects/'.$project->id)->assertOk()->assertSee('Store revenue')->assertSee('€125.50');
        $export = $this->get('/w/studio/export/history')->assertOk()->streamedContent();
        $this->assertStringContainsString('store_revenue,2026-11-01,2026-11-01,80,no,EUR', $export);
        $this->assertStringContainsString('store_orders,2026-11-03,2026-11-03,1,no,', $export);
    }

    public function test_people_who_cannot_manage_access_cannot_add_change_or_import_a_shop(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Studio', 'slug' => 'studio']);
        $scope = app(WorkspaceContext::class);
        $project = $scope->run($workspace, fn () => Project::create(['name' => 'Prints', 'slug' => 'prints']));
        $source = $scope->run($workspace, fn () => Source::create(['project_id' => $project->id, 'kind' => 'prestashop', 'label' => 'Shop',
            'reporting_timezone' => 'Europe/Vienna', 'config' => ['shop_id' => '1', 'days' => 30], 'identity_hash' => str_repeat('a', 64)]));
        $this->app->instance(WorkspaceAccess::class, new class extends PersonalWorkspaceAccess
        {
            public function allows(Authenticatable $user, Workspace $workspace, string $ability): bool
            {
                return $ability !== 'manage_access' && parent::allows($user, $workspace, $ability);
            }
        });

        $this->actingAs($owner)->get('/w/studio/sources/new?project='.$project->id)->assertOk()->assertSee('Google Analytics 4')->assertDontSee('PrestaShop')->assertDontSee('Paddle Billing');
        $this->post('/w/studio/sources/setup', ['action' => 'provider', 'kind' => 'prestashop'])->assertForbidden();
        $this->post('/w/studio/sources', ['project_id' => $project->id, 'kind' => 'prestashop', 'label' => 'Shop', 'reporting_timezone' => 'UTC', 'config' => ['shop_id' => '2']])
            ->assertSessionHasErrors('kind');
        $this->patch('/w/studio/sources/'.$source->id, ['label' => 'Renamed', 'version' => $scope->run($workspace, fn () => $source->fresh()->version)])->assertForbidden();
        $this->assertSame('Shop', $scope->run($workspace, fn () => $source->fresh()->label));

        $document = ['format' => 'metribay', 'version' => 1, 'projects' => [['slug' => 'other', 'name' => 'Other', 'archived' => false,
            'sources' => [['kind' => 'prestashop', 'label' => 'Shop', 'config' => ['shop_id' => '1'], 'reporting_timezone' => 'UTC', 'enabled' => true]]]]];
        $this->expectExceptionMessage('confirm sharing before importing revenue sources');
        $scope->run($workspace, fn () => app(ConfigurationTransfer::class)->import($document));
    }
}
