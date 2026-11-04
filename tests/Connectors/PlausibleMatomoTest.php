<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\DiscoveryException;
use Metribay\Core\Connectors\MatomoConnector;
use Metribay\Core\Connectors\PlausibleConnector;
use Metribay\Core\Connectors\ResourceDiscovery;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Services\ProviderCatalog;

require_once __DIR__.'/FixtureTestCase.php';

final class PlausibleMatomoTest extends FixtureTestCase
{
    public function test_plausible_maps_daily_rows_keeps_gaps_and_stores_separate_period_visitors(): void
    {
        Http::fake(function (Request $request) {
            $this->assertStringNotContainsString('stats-key', $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer stats-key'));
            if (str_contains($request->url(), '/api/v1/sites/example.com')) {
                return Http::response(['domain' => 'Example.com', 'timezone' => 'Etc/UTC']);
            }
            $body = $request->data();
            $this->assertSame('example.com', $body['site_id']);
            if (($body['dimensions'] ?? null) === ['time:day']) {
                $this->assertSame(['2026-10-23', '2026-10-25'], $body['date_range']);

                return Http::response([
                    'query' => ['site_id' => 'example.com', 'metrics' => ['visitors', 'visits', 'pageviews']],
                    'meta' => ['metric_warnings' => []],
                    'results' => [
                        ['dimensions' => ['2026-10-23'], 'metrics' => [0, 0, 0]],
                        ['dimensions' => ['2026-10-25'], 'metrics' => [4, 5, 9]],
                    ],
                ]);
            }
            $visitors = ['query' => ['site_id' => 'example.com', 'metrics' => ['visitors']], 'meta' => ['metric_warnings' => []], 'results' => []];
            if ($body['date_range'] === ['2026-10-23', '2026-10-25']) {
                $visitors['results'] = [['dimensions' => [], 'metrics' => [7]]];
            } elseif ($body['date_range'] === ['2026-10-20', '2026-10-22']) {
                $visitors['results'] = [['dimensions' => [], 'metrics' => [0]]];
            }

            return Http::response($visitors);
        });
        $result = (new PlausibleConnector($this->http()))->collect($this->plausible(), ['base_url' => 'https://plausible.example.com/', 'api_key' => 'stats-key'], $this->now());
        $this->assertSame(['pageviews' => 0.0, 'visitors' => 0.0, 'visits' => 0.0], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame(['pageviews' => 9.0, 'visitors' => 4.0, 'visits' => 5.0], $this->valuesOn($result->daily, '2026-10-25'));
        $this->assertFalse($result->daily[0]['is_partial']);
        $this->assertTrue($result->daily[array_key_last($result->daily)]['is_partial']);
        $this->assertSame([
            ['metric' => 'visitors', 'start_date' => '2026-10-23', 'end_date' => '2026-10-25', 'value' => 7.0, 'is_partial' => true],
            ['metric' => 'visitors', 'start_date' => '2026-10-20', 'end_date' => '2026-10-22', 'value' => 0.0, 'is_partial' => false],
        ], $result->periods);
        $this->assertNotSame(4.0, $result->periods[0]['value']);
    }

    public function test_plausible_timezone_mismatch_stops_before_the_stats_query(): void
    {
        Http::fake(['plausible.example.com/api/v1/sites/*' => Http::response(['domain' => 'example.com', 'timezone' => 'Europe/Berlin'])]);
        try {
            (new PlausibleConnector($this->http()))->collect($this->plausible(), ['base_url' => 'https://plausible.example.com', 'api_key' => 'stats-key'], $this->now());
            $this->fail('A different site timezone must stop collection.');
        } catch (ConnectorException $exception) {
            $this->assertStringContainsString('timezone', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_plausible_missing_site_is_not_an_empty_success(): void
    {
        Http::fake(['plausible.example.com/api/v1/sites/*' => Http::response(['secret' => 'hidden-body'], 404)]);
        try {
            (new PlausibleConnector($this->http()))->collect($this->plausible(), ['base_url' => 'https://plausible.example.com', 'api_key' => 'stats-key'], $this->now());
            $this->fail('A missing site must not collect as an empty success.');
        } catch (ConnectorException $exception) {
            $this->assertStringNotContainsString('hidden-body', $exception->getMessage());
            $this->assertStringContainsString('not visible', $exception->getMessage());
        }
    }

    public function test_plausible_rejects_a_rewritten_query_and_metric_warnings_without_leaking_them(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/api/v1/sites/')) {
                return Http::response(['domain' => 'example.com', 'timezone' => 'UTC']);
            }

            return Http::response([
                'query' => ['site_id' => 'example.com', 'metrics' => ['visitors', 'visits', 'pageviews']],
                'meta' => ['metric_warnings' => ['visitors' => 'provider-secret']],
                'results' => [],
            ]);
        });
        try {
            (new PlausibleConnector($this->http()))->collect($this->plausible(), ['base_url' => 'https://plausible.example.com', 'api_key' => 'stats-key'], $this->now());
            $this->fail('Metric warnings must fail the collection.');
        } catch (ConnectorException $exception) {
            $this->assertStringNotContainsString('provider-secret', $exception->getMessage());
        }
    }

    public function test_plausible_lists_sites_by_domain_and_keeps_the_key_out_of_the_url(): void
    {
        Http::fake(function (Request $request) {
            $this->assertStringNotContainsString('stats-key', $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer stats-key'));
            if (! str_contains($request->url(), 'after=')) {
                return Http::response(['sites' => [
                    ['domain' => 'Example.com', 'timezone' => 'America/Denver'],
                    ['domain' => 'other.example', 'timezone' => 'UTC'],
                ], 'meta' => ['after' => 'cursor-1']]);
            }

            return Http::response(['sites' => [['domain' => 'example.com', 'timezone' => 'America/Denver']], 'meta' => ['after' => null]]);
        });
        $rows = (new ResourceDiscovery($this->http()))->discover('plausible', ['base_url' => 'https://plausible.example.com/', 'api_key' => 'stats-key']);
        $this->assertSame(['example.com', 'other.example'], array_column($rows, 'id'));
        $this->assertSame('America/Denver', $rows[0]['timezone']);
        Http::assertSentCount(2);
    }

    public function test_plausible_discovery_failures_are_actionable_and_hide_the_body(): void
    {
        Http::fake(['plausible.example.com/*' => Http::response(['message' => 'stats-key'], 401)]);
        try {
            (new ResourceDiscovery($this->http()))->discover('plausible', ['base_url' => 'https://plausible.example.com', 'api_key' => 'stats-key']);
            $this->fail('A rejected key must not look like an empty site list.');
        } catch (DiscoveryException $exception) {
            $this->assertStringContainsString('HTTP 401', $exception->getMessage());
            $this->assertStringNotContainsString('stats-key', $exception->getMessage());
        }
    }

    public function test_matomo_reads_visits_and_page_views_separately_and_leaves_period_visitors_unknown(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('POST', $request->method());
            $this->assertStringNotContainsString('auth-token', $request->url());
            $this->assertStringEndsWith('/index.php', $request->url());
            $this->assertSame('auth-token', $request['token_auth']);
            $this->assertSame('0', $request['format_metrics']);
            $this->assertNotSame('range', $request['period'] ?? null);
            if ($request['method'] === 'SitesManager.getSiteFromId') {
                return Http::response(['idsite' => '4', 'timezone' => 'Europe/Berlin', 'main_url' => 'https://nordlicht.example']);
            }
            if ($request['method'] === 'VisitsSummary.get') {
                return Http::response([
                    '2026-10-23' => [],
                    '2026-10-25' => ['nb_uniq_visitors' => 3, 'nb_visits' => 4, 'sum_daily_nb_uniq_visitors' => 99],
                ]);
            }

            return Http::response([
                '2026-10-23' => [],
                '2026-10-25' => ['nb_pageviews' => 8],
            ]);
        });
        $result = (new MatomoConnector($this->http()))->collect(
            new SourceConfiguration('matomo', ['site_id' => '4', 'days' => 3, 'period_days' => [3]], 'Europe/Berlin'),
            ['base_url' => 'https://matomo.example.com/', 'token_auth' => 'auth-token'],
            $this->now(),
        );
        $this->assertSame(['pageviews' => 0.0, 'visitors' => 0.0, 'visits' => 0.0], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame(['pageviews' => 8.0, 'visitors' => 3.0, 'visits' => 4.0], $this->valuesOn($result->daily, '2026-10-25'));
        $this->assertTrue($result->daily[array_key_last($result->daily)]['is_partial']);
        $this->assertSame([], $result->periods);
        $this->assertNotContains(99.0, array_column($result->daily, 'value'));
    }

    public function test_matomo_inconsistent_days_and_error_bodies_fail_without_leaking_the_response(): void
    {
        Http::fake(function (Request $request) {
            if ($request['method'] === 'SitesManager.getSiteFromId') {
                return Http::response(['idsite' => 4, 'timezone' => 'UTC']);
            }
            if ($request['method'] === 'VisitsSummary.get') {
                return Http::response(['2026-10-25' => ['nb_uniq_visitors' => 1, 'nb_visits' => 1]]);
            }

            return Http::response(['2026-10-25' => []]);
        });
        try {
            (new MatomoConnector($this->http()))->collect($this->matomo(), ['base_url' => 'https://matomo.example.com', 'token_auth' => 'auth-token'], $this->now());
            $this->fail('A day present in only one report must not be stored.');
        } catch (ConnectorException $exception) {
            $this->assertStringContainsString('inconsistent', $exception->getMessage());
        }
    }

    public function test_matomo_http_errors_do_not_include_the_response_body(): void
    {
        Http::fake(['matomo.example.com/*' => Http::response(['result' => 'error', 'message' => 'site 4 is private'], 401)]);
        try {
            (new MatomoConnector($this->http()))->collect($this->matomo(), ['base_url' => 'https://matomo.example.com', 'token_auth' => 'auth-token'], $this->now());
            $this->fail('An HTTP error must not become an empty success.');
        } catch (ConnectorException $exception) {
            $this->assertStringNotContainsString('private', $exception->getMessage());
            $this->assertStringContainsString('HTTP 401', $exception->getMessage());
        }
    }

    public function test_matomo_lists_viewable_sites_and_hides_the_token(): void
    {
        Http::fake(function (Request $request) {
            $this->assertStringNotContainsString('auth-token', $request->url());
            $this->assertSame('auth-token', $request['token_auth']);
            $this->assertSame('SitesManager.getSitesWithAtLeastViewAccess', $request['method']);

            return Http::response([
                '1' => ['idsite' => 1, 'name' => 'Shop &amp; <b>Co</b>', 'timezone' => 'Europe/Berlin', 'main_url' => 'https://shop.example/'],
                '2' => ['idsite' => '2', 'name' => 'Docs', 'timezone' => 'UTC', 'main_url' => 'not a url'],
            ]);
        });
        $rows = (new ResourceDiscovery($this->http()))->discover('matomo', ['base_url' => 'https://analytics.example.com/matomo', 'token_auth' => 'auth-token']);
        $this->assertSame(['1', '2'], array_column($rows, 'id'));
        $this->assertSame('Shop & Co', $rows[0]['name']);
        $this->assertSame('shop.example', $rows[0]['domain']);
        $this->assertSame('', $rows[1]['domain']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://analytics.example.com/matomo/index.php');
    }

    public function test_matomo_stops_listing_when_the_account_can_view_too_many_sites(): void
    {
        $sites = [];
        for ($id = 1; $id <= 2000; $id++) {
            $sites[] = ['idsite' => $id, 'name' => 'Site '.$id, 'timezone' => 'UTC', 'main_url' => 'https://example.com'];
        }
        Http::fake(['analytics.example.com/*' => Http::response($sites)]);
        $this->expectExceptionMessage('Matomo has too many sites to list.');
        (new ResourceDiscovery($this->http()))->discover('matomo', ['base_url' => 'https://analytics.example.com', 'token_auth' => 'auth-token']);
    }

    public function test_catalog_accepts_public_https_credentials_and_site_identity(): void
    {
        $this->assertSame(
            ['base_url' => 'https://plausible.io', 'api_key' => 'stats-key'],
            ProviderCatalog::credentials('plausible', ['base_url' => ' https://plausible.io/ ', 'api_key' => ' stats-key ']),
        );
        $this->assertSame(
            ['base_url' => 'https://example.matomo.cloud', 'token_auth' => 'auth-token'],
            ProviderCatalog::credentials('matomo', ['base_url' => 'https://example.matomo.cloud/', 'token_auth' => ' auth-token ']),
        );
        $this->assertSame(['site_id' => 'example.com', 'days' => 30], ProviderCatalog::source('plausible', ['site_id' => 'Example.COM', 'days' => 30], 'America/Denver'));
        $this->assertSame(['site_id' => '4', 'days' => 180], ProviderCatalog::source('matomo', ['site_id' => '4'], 'Europe/Berlin'));
        $this->assertTrue(ProviderCatalog::usesSiteTimezone('plausible'));
        $this->assertTrue(ProviderCatalog::usesSiteTimezone('matomo'));
        $this->assertFalse(ProviderCatalog::usesSiteTimezone('umami'));
        $this->assertSame(['visitors', 'visits', 'pageviews'], ProviderCatalog::metricKeys('plausible'));
        foreach (['http://plausible.example.com', 'https://plausible.example.com/?key=stats-key', 'https://plausible.example.com/../secret'] as $url) {
            try {
                ProviderCatalog::credentials('plausible', ['base_url' => $url, 'api_key' => 'stats-key']);
                $this->fail($url.' must be rejected.');
            } catch (ValidationException) {
            }
        }
        try {
            ProviderCatalog::source('matomo', ['site_id' => '0'], 'UTC');
            $this->fail('A zero site id must be rejected.');
        } catch (ValidationException) {
        }
    }

    private function plausible(): SourceConfiguration
    {
        return new SourceConfiguration('plausible', ['site_id' => 'example.com', 'days' => 3, 'period_days' => [3]]);
    }

    private function matomo(): SourceConfiguration
    {
        return new SourceConfiguration('matomo', ['site_id' => '4', 'days' => 3, 'period_days' => [3]]);
    }
}
