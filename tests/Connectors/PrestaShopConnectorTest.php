<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\DiscoveryException;
use Metribay\Core\Connectors\PrestaShopClient;
use Metribay\Core\Connectors\PrestaShopConnector;
use Metribay\Core\Connectors\ResourceDiscovery;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Services\ProviderCatalog;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__.'/FixtureTestCase.php';

final class PrestaShopConnectorTest extends FixtureTestCase
{
    private const KEY = 'SYNTHETICPRESTASHOPWEBSERVICE001';

    private const CREDENTIALS = ['base_url' => 'https://shop.example.com/boutique', 'api_key' => self::KEY];

    private const CURRENCIES = ['currencies' => [
        ['id' => 1, 'iso_code' => 'EUR', 'conversion_rate' => '1.000000', 'active' => '1', 'deleted' => '0'],
        ['id' => 2, 'iso_code' => 'CHF', 'conversion_rate' => '0.940000', 'active' => '1', 'deleted' => '0'],
        // An old currency that once had rate 1 and was deleted does not compete with the default.
        ['id' => 3, 'iso_code' => 'GBP', 'conversion_rate' => '1.000000', 'active' => '0', 'deleted' => '1'],
    ]];

    public function test_valid_orders_and_revenue_per_shop_day_in_the_default_currency(): void
    {
        $requests = [];
        Http::fake(function (Request $request) use (&$requests) {
            $this->assertSame('GET', $request->method());
            $this->assertStringNotContainsString(self::KEY, $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Basic '.base64_encode(self::KEY.':')));
            $this->assertSame('JSON', $request['output_format']);
            $this->assertSame('3', $request['id_shop']);
            $path = parse_url($request->url(), PHP_URL_PATH);
            $requests[] = $path;
            if ($path === '/boutique/api/currencies') {
                $this->assertSame('[id,iso_code,conversion_rate,active,deleted]', $request['display']);

                return Http::response(self::CURRENCIES);
            }
            $this->assertSame('/boutique/api/orders', $path);
            $this->assertSame('[id,date_add,total_paid_tax_excl,conversion_rate]', $request['display']);
            $this->assertSame(['[3]', '[1]', '[2026-10-22 00:00:00,2026-10-25 23:59:59]'], [$request['filter[id_shop]'], $request['filter[valid]'], $request['filter[date_add]']]);
            $this->assertSame('1', $request['date']);
            $this->assertSame('[id_ASC]', $request['sort']);
            $this->assertSame('0,1000', $request['limit']);

            return Http::response(['orders' => [
                ['id' => 11, 'date_add' => '2026-10-22 00:10:00', 'total_paid_tax_excl' => '40.000000', 'conversion_rate' => '1.000000'],
                ['id' => 12, 'date_add' => '2026-10-22 23:50:00', 'total_paid_tax_excl' => '9.990000', 'conversion_rate' => '1.000000'],
                // 47 CHF at 0.94 CHF per EUR is 50 EUR.
                ['id' => 13, 'date_add' => '2026-10-24 12:00:00', 'total_paid_tax_excl' => '47.000000', 'conversion_rate' => '0.940000'],
                ['id' => 14, 'date_add' => '2026-10-25 09:00:00', 'total_paid_tax_excl' => 12.5, 'conversion_rate' => 1],
            ]]);
        });

        $result = (new PrestaShopConnector($this->http()))->collect($this->source(), self::CREDENTIALS, $this->now());

        $this->assertSame(['/boutique/api/currencies', '/boutique/api/orders'], $requests);
        $this->assertSame(['store_orders' => 2.0, 'store_revenue' => 49.99], $this->valuesOn($result->daily, '2026-10-22'));
        $this->assertSame(['store_orders' => 0.0, 'store_revenue' => 0.0], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame(['store_orders' => 1.0, 'store_revenue' => 50.0], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame(['store_orders' => 1.0, 'store_revenue' => 12.5], $this->valuesOn($result->daily, '2026-10-25'));
        $this->assertCount(8, $result->daily);
        foreach ($result->daily as $row) {
            $this->assertSame($row['date'] === '2026-10-25', $row['is_partial']);
            $this->assertSame($row['metric'] === 'store_revenue' ? ['currency' => 'EUR'] : null, $row['context'] ?? null);
        }
    }

    public function test_orders_are_read_page_by_page_and_an_empty_shop_records_zero(): void
    {
        $limits = [];
        Http::fake(function (Request $request) use (&$limits) {
            if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/currencies')) {
                return Http::response(self::CURRENCIES);
            }
            $limits[] = $request['limit'];
            $rows = $request['limit'] === '0,1000'
                ? array_map(fn (int $id): array => ['id' => $id, 'date_add' => '2026-10-23 10:00:00', 'total_paid_tax_excl' => '1.00', 'conversion_rate' => '1'], range(1, PrestaShopClient::PAGE_SIZE))
                : [['id' => 1001, 'date_add' => '2026-10-24 10:00:00', 'total_paid_tax_excl' => '2.00', 'conversion_rate' => '1']];

            return Http::response(['orders' => $rows]);
        });
        $result = (new PrestaShopConnector($this->http()))->collect($this->source(), self::CREDENTIALS, $this->now());

        $this->assertSame(['0,1000', '1000,1000'], $limits);
        $this->assertSame(['store_orders' => 1000.0, 'store_revenue' => 1000.0], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame(['store_orders' => 1.0, 'store_revenue' => 2.0], $this->valuesOn($result->daily, '2026-10-24'));

        // PrestaShop answers an empty list with [] instead of {"orders": []}.
        Http::fake(fn (Request $request) => Http::response(str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/currencies') ? self::CURRENCIES : []));
        $result = (new PrestaShopConnector($this->http()))->collect($this->source(), self::CREDENTIALS, $this->now());
        $this->assertSame(['store_orders' => 0.0, 'store_revenue' => 0.0], $this->valuesOn($result->daily, '2026-10-22'));
        $this->assertCount(8, $result->daily);
    }

    public function test_the_default_currency_must_be_unambiguous(): void
    {
        Http::fake(fn () => Http::response(['currencies' => [
            ['id' => 1, 'iso_code' => 'EUR', 'conversion_rate' => '1.000000', 'active' => '1', 'deleted' => '0'],
            ['id' => 2, 'iso_code' => 'USD', 'conversion_rate' => '1.000000', 'active' => '1', 'deleted' => '0'],
        ]]));

        $this->expectExceptionMessage('could not tell the PrestaShop default currency');
        (new PrestaShopConnector($this->http()))->collect($this->source(), self::CREDENTIALS, $this->now());
    }

    /** @return iterable<string, array{0: mixed, 1: int, 2: string}> */
    public static function failures(): iterable
    {
        yield 'rejected key' => [null, 401, 'PrestaShop rejected the webservice key.'];
        yield 'shop not allowed' => [null, 403, 'may not read this shop'];
        yield 'webservice off' => [null, 404, 'did not answer at /api'];
        yield 'maintenance' => [null, 503, 'maintenance mode'];
        yield 'login page' => ['<html>Sign in</html>', 200, 'did not return JSON'];
        yield 'order outside the window' => [['orders' => [['id' => 1, 'date_add' => '2026-10-21 23:59:59', 'total_paid_tax_excl' => '1', 'conversion_rate' => '1']]], 200, 'outside the requested dates'];
        yield 'order without a rate' => [['orders' => [['id' => 1, 'date_add' => '2026-10-23 10:00:00', 'total_paid_tax_excl' => '1', 'conversion_rate' => '0']]], 200, 'without a currency rate'];
        yield 'malformed date' => [['orders' => [['id' => 1, 'date_add' => '2026-10-23T10:00:00Z', 'total_paid_tax_excl' => '1', 'conversion_rate' => '1']]], 200, 'invalid order date'];
        yield 'negative total' => [['orders' => [['id' => 1, 'date_add' => '2026-10-23 10:00:00', 'total_paid_tax_excl' => '-1', 'conversion_rate' => '1']]], 200, 'invalid metric value'];
        yield 'orders not a list' => [['orders' => ['id' => 1]], 200, 'invalid list'];
    }

    #[DataProvider('failures')]
    public function test_provider_failures_fail_the_collection_with_a_safe_message(mixed $orders, int $status, string $message): void
    {
        Http::fake(fn (Request $request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/currencies')
            ? Http::response(self::CURRENCIES)
            : Http::response(is_array($orders) ? json_encode($orders) : (string) $orders, $status));

        try {
            (new PrestaShopConnector($this->http()))->collect($this->source(), self::CREDENTIALS, $this->now());
            $this->fail('Expected a connector failure.');
        } catch (ConnectorException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
            $this->assertStringNotContainsString(self::KEY, $exception->getMessage());
        }
    }

    public function test_shop_address_and_key_are_normalized_and_checked(): void
    {
        $this->assertSame('https://shop.example.com', PrestaShopClient::base('https://Shop.example.com/'));
        $this->assertSame('https://shop.example.com/boutique', PrestaShopClient::base(' https://shop.example.com/boutique/api/ '));
        foreach (['http://shop.example.com', 'https://shop.example.com:8443', 'https://user@shop.example.com', 'https://shop.example.com/?a=1', 'https://shop.example.com/../x', ''] as $invalid) {
            try {
                PrestaShopClient::base($invalid);
                $this->fail($invalid);
            } catch (ConnectorException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(self::KEY, PrestaShopClient::key(' '.self::KEY.' '));
        foreach ([substr(self::KEY, 1), 'SYNTHETICPRESTASHOPWEBSERVICE:01', 'SYNTHETICPRESTASHOPWEBSERVICE 01'] as $invalid) {
            try {
                PrestaShopClient::key($invalid);
                $this->fail($invalid);
            } catch (ConnectorException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(['base_url' => 'https://shop.example.com', 'api_key' => self::KEY],
            ProviderCatalog::credentials('prestashop', ['base_url' => 'https://shop.example.com/api', 'api_key' => self::KEY]));
        $this->expectException(ValidationException::class);
        ProviderCatalog::credentials('prestashop', ['base_url' => 'https://shop.example.com', 'api_key' => 'short']);
    }

    public function test_a_source_needs_a_numeric_shop_and_keeps_the_shop_timezone(): void
    {
        $this->assertSame(['shop_id' => '1', 'days' => 30], ProviderCatalog::source('prestashop', ['shop_id' => 1, 'days' => 30], 'Europe/Vienna'));
        $this->assertTrue(ProviderCatalog::revenue('prestashop'));
        $this->assertSame(['store_revenue', 'store_orders'], ProviderCatalog::metricKeys('prestashop'));
        $this->expectException(ValidationException::class);
        ProviderCatalog::source('prestashop', ['shop_id' => 'main'], 'Europe/Vienna');
    }

    public function test_discovery_lists_active_shops_without_a_timezone(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('https://shop.example.com/boutique/api/shops', strtok($request->url(), '?'));
            $this->assertSame('[id,name,active]', $request['display']);
            $this->assertArrayNotHasKey('id_shop', $request->data());

            return Http::response(['shops' => [
                ['id' => 1, 'name' => 'Prints &amp; posters', 'active' => '1'],
                ['id' => 2, 'name' => 'Closed', 'active' => '0'],
                ['id' => '3', 'name' => '', 'active' => '1'],
            ]]);
        });

        $this->assertSame([
            ['id' => '1', 'name' => 'Prints & posters', 'domain' => 'shop.example.com · Shop ID 1', 'timezone' => ''],
            ['id' => '3', 'name' => 'Shop 3', 'domain' => 'shop.example.com · Shop ID 3', 'timezone' => ''],
        ], (new ResourceDiscovery($this->http()))->discover('prestashop', self::CREDENTIALS));
    }

    public function test_discovery_failure_suggests_manual_entry(): void
    {
        Http::fake(fn () => Http::response('', 401));

        try {
            (new ResourceDiscovery($this->http()))->discover('prestashop', self::CREDENTIALS);
            $this->fail('Expected a discovery failure.');
        } catch (DiscoveryException $exception) {
            $this->assertStringContainsString('PrestaShop rejected the webservice key.', $exception->getMessage());
            $this->assertStringContainsString('A shop without multistore has ID 1.', $exception->getMessage());
        }
    }

    private function source(): SourceConfiguration
    {
        return new SourceConfiguration('prestashop', ['shop_id' => '3', 'days' => 4], 'Europe/Vienna');
    }
}
