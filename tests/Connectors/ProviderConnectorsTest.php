<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\BingConnector;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\Ga4Connector;
use Metribay\Core\Connectors\GoogleAuthorizationExpired;
use Metribay\Core\Connectors\GoogleToken;
use Metribay\Core\Connectors\GscConnector;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Connectors\UmamiConnector;
use Metribay\Core\Connectors\WpOrgConnector;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__.'/FixtureTestCase.php';

final class ProviderConnectorsTest extends FixtureTestCase
{
    public function test_wordpress_verifies_slug_then_preserves_zeros_and_gaps_and_marks_today_partial(): void
    {
        Http::fake([
            'api.wordpress.org/plugins/info/*' => Http::response(['slug' => 'example-plugin']),
            'api.wordpress.org/stats/*' => Http::response(['2026-10-23' => '0', '2026-10-25' => 12]),
        ]);
        $result = (new WpOrgConnector($this->http()))->collect(new SourceConfiguration('wporg', ['slug' => 'example-plugin', 'days' => 3]), [], $this->now());
        $this->assertCount(2, $result->daily);
        $this->assertSame(['downloads' => 0.0], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertTrue($result->daily[1]['is_partial']);
        Http::assertSentCount(2);
    }

    public function test_wordpress_documented_limit_config_controls_the_outbound_history_window(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/info/')) {
                return Http::response(['slug' => 'example-plugin']);
            }
            $this->assertSame(7, $request['limit']);

            return Http::response(['2026-10-24' => '10']);
        });
        $result = (new WpOrgConnector($this->http()))->collect(new SourceConfiguration('wporg', ['slug' => 'example-plugin', 'limit' => 7]), [], $this->now());
        $this->assertSame(['downloads' => 10.0], $this->valuesOn($result->daily, '2026-10-24'));
        Http::assertSentCount(2);
    }

    public function test_invalid_wordpress_slug_never_uses_the_plausible_downloads_endpoint(): void
    {
        Http::fake(['api.wordpress.org/plugins/info/*' => Http::response(['error' => 'Plugin not found'], 404)]);
        $this->expectException(ConnectorException::class);
        try {
            (new WpOrgConnector($this->http()))->collect(new SourceConfiguration('wporg', ['slug' => 'does-not-exist']), [], $this->now());
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_wordpress_one_day_requests_are_widened_and_unknown_fields_are_rejected(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/info/')) {
                return Http::response(['slug' => 'example-plugin']);
            }
            $this->assertSame(2, $request['limit']);

            return Http::response(['2026-10-25' => 'NaN']);
        });
        $this->expectException(ConnectorException::class);
        (new WpOrgConnector($this->http()))->collect(new SourceConfiguration('wporg', ['slug' => 'example-plugin', 'days' => 1]), [], $this->now());
    }

    public function test_bing_preserves_independent_traffic_and_crawl_gaps_and_dotnet_offset_dates(): void
    {
        $date23 = $this->now()->subDays(2)->startOfDay()->getTimestampMs();
        $date24 = $this->now()->subDay()->startOfDay()->getTimestampMs();
        Http::fake([
            '*GetRankAndTrafficStats*' => Http::response(['d' => [
                ['Date' => '/Date('.$date23.')/', 'Impressions' => 0, 'Clicks' => 0],
                ['Date' => '/Date('.$date24.'-0700)/', 'Impressions' => 10, 'Clicks' => 2],
            ]]),
            '*GetCrawlStats*' => Http::response(['d' => [['Date' => '/Date('.$date23.')/', 'InIndex' => 20]]]),
            '*GetQueryStats*' => Http::response(['d' => []]),
        ]);
        $result = (new BingConnector($this->http()))->collect(new SourceConfiguration('bing', ['site_url' => 'https://example.com', 'days' => 1]), ['api_key' => 'synthetic-secret'], $this->now());
        $this->assertSame(['clicks' => 0.0, 'impressions' => 0.0, 'indexed_pages' => 20.0], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame(['clicks' => 2.0, 'impressions' => 10.0], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame([], $result->periods);
        $this->assertSame([], $result->queries);
    }

    public function test_bing_sums_weekly_queries_into_windows_that_end_on_the_latest_week(): void
    {
        $week = fn (int $weeksAgo): string => '/Date('.CarbonImmutable::parse('2026-10-19')->subWeeks($weeksAgo)->getTimestampMs().')/';
        Http::fake([
            '*GetRankAndTrafficStats*' => Http::response(['d' => []]),
            '*GetCrawlStats*' => Http::response(['d' => []]),
            '*GetQueryStats*' => Http::response(['d' => [
                ['Query' => 'metribay', 'Date' => $week(0), 'Impressions' => 30, 'Clicks' => 6, 'AvgImpressionPosition' => 2, 'AvgClickPosition' => 2],
                ['Query' => 'metribay', 'Date' => $week(1), 'Impressions' => 10, 'Clicks' => 1, 'AvgImpressionPosition' => 6, 'AvgClickPosition' => -1],
                ['Query' => 'dashboard', 'Date' => $week(2), 'Impressions' => 5, 'Clicks' => 0, 'AvgImpressionPosition' => -1, 'AvgClickPosition' => -1],
                ['Query' => 'metribay', 'Date' => $week(5), 'Impressions' => 8, 'Clicks' => 2, 'AvgImpressionPosition' => 9, 'AvgClickPosition' => 9],
            ]]),
        ]);
        $result = (new BingConnector($this->http()))->collect(new SourceConfiguration('bing', ['site_url' => 'https://example.com']), ['api_key' => 'synthetic-secret'], $this->now());
        $find = fn (int $days, bool $previous, string $query): ?array => collect($result->queries)->first(fn (array $row): bool => $row['days'] === $days && $row['previous'] === $previous && $row['query'] === $query);

        // One week: only the latest week.
        $this->assertSame(['clicks' => 6.0, 'impressions' => 30.0, 'position' => 2.0], array_intersect_key($find(7, false, 'metribay'), array_flip(['clicks', 'impressions', 'position'])));
        $this->assertSame(['2026-10-13', '2026-10-19'], [$find(7, false, 'metribay')['start_date'], $find(7, false, 'metribay')['end_date']]);
        $this->assertSame(1.0, $find(7, true, 'metribay')['clicks']);
        $this->assertNull($find(7, false, 'dashboard'));
        // Four weeks: summed, position weighted by impressions (2×30 + 6×10) / 40 = 3, unknown -1 stays unknown.
        $this->assertSame(7.0, $find(30, false, 'metribay')['clicks']);
        $this->assertSame(3.0, $find(30, false, 'metribay')['position']);
        $this->assertNull($find(30, false, 'dashboard')['position']);
        $this->assertSame('2026-09-22', $find(30, false, 'metribay')['start_date']);
        // The previous four weeks contain week five; only queries of the current window are compared.
        $this->assertSame(2.0, $find(30, true, 'metribay')['clicks']);
        $this->assertNull($find(30, true, 'dashboard'));
    }

    public function test_bing_archives_complete_months_by_the_date_of_each_week(): void
    {
        $row = fn (string $query, string $date, int $clicks): array => ['Query' => $query, 'Date' => '/Date('.CarbonImmutable::parse($date)->getTimestampMs().')/', 'Impressions' => 10 * $clicks, 'Clicks' => $clicks, 'AvgImpressionPosition' => 4, 'AvgClickPosition' => 4];
        Http::fake([
            '*GetRankAndTrafficStats*' => Http::response(['d' => []]),
            '*GetCrawlStats*' => Http::response(['d' => []]),
            '*GetQueryStats*' => Http::response(['d' => [
                // August starts after its first week: Bing no longer keeps that week, so the month is incomplete.
                $row('metribay', '2026-08-17', 1),
                $row('metribay', '2026-09-07', 2), $row('metribay', '2026-09-14', 3), $row('dashboard', '2026-09-28', 1),
                $row('metribay', '2026-10-05', 4), $row('metribay', '2026-10-26', 5),
                // November is not complete yet.
                $row('metribay', '2026-11-02', 6),
            ]]),
        ]);
        $result = (new BingConnector($this->http()))->collect(new SourceConfiguration('bing', ['site_url' => 'https://example.com'], 'UTC', null, ['2026-10']), ['api_key' => 'synthetic-secret'], $this->now()->addDays(10));

        $this->assertSame(['2026-09-01'], array_column($result->queryMonths, 'month'));
        $september = $result->queryMonths[0];
        $this->assertSame(['2026-09-07', '2026-09-28', 3], [$september['start_date'], $september['end_date'], $september['weeks']]);
        $this->assertSame([['query' => 'metribay', 'clicks' => 5.0, 'impressions' => 50.0, 'position' => 4.0], ['query' => 'dashboard', 'clicks' => 1.0, 'impressions' => 10.0, 'position' => 4.0]], $september['rows']);
    }

    public function test_bing_provider_errors_never_echo_query_credentials(): void
    {
        Http::fake(['*' => Http::response(['ErrorCode' => 14, 'Message' => 'synthetic-secret'], 400)]);
        try {
            (new BingConnector($this->http()))->collect(new SourceConfiguration('bing', ['site_url' => 'https://example.com']), ['api_key' => 'synthetic-secret'], $this->now());
            $this->fail('Expected access failure.');
        } catch (ConnectorException $exception) {
            $this->assertStringNotContainsString('synthetic-secret', $exception->getMessage());
            $this->assertStringContainsString('access', $exception->getMessage());
        }
    }

    public function test_bing_a_missing_count_or_duplicate_date_is_an_error_not_zero(): void
    {
        Http::fake(['*' => Http::response(['d' => [['Date' => '/Date('.$this->now()->subDay()->startOfDay()->getTimestampMs().')/', 'Impressions' => 20]]])]);
        $this->expectException(ConnectorException::class);
        (new BingConnector($this->http()))->collect(new SourceConfiguration('bing', ['site_url' => 'https://example.com']), ['api_key' => 'secret'], $this->now());
    }

    public function test_umami_maps_sessions_to_visitors_and_uses_separate_stats_for_visits_and_period_users(): void
    {
        $calls = [];
        Http::fake(function (Request $request) use (&$calls) {
            $calls[] = $request;
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/login')) {
                $this->assertSame('secret-password', $request['password']);

                return Http::response(['token' => 'synthetic-bearer']);
            }
            $this->assertSame('Bearer synthetic-bearer', $request->header('Authorization')[0]);
            if (str_ends_with($path, '/site-one')) {
                return Http::response(['id' => 'site-one']);
            }
            if (str_ends_with($path, '/pageviews')) {
                $this->assertSame('UTC', $request['timezone']);
                $this->assertSame($this->now()->subDays(2)->startOfDay()->getTimestampMs(), $request['startAt']);

                return Http::response(['pageviews' => [
                    ['x' => '2026-10-22 00:00:00', 'y' => 999],
                    ['x' => '2026-10-23 00:00:00', 'y' => 10], ['x' => '2026-10-25 00:00:00', 'y' => 3]],
                    'sessions' => [['x' => '2026-10-23 00:00:00', 'y' => 8], ['x' => '2026-10-25 00:00:00', 'y' => 2]]]);
            }
            $span = ($request['endAt'] - $request['startAt']) / 86400000;

            return Http::response(['visits' => 11, 'visitors' => $span > 1 ? 123 : 8]);
        });
        $source = new SourceConfiguration('umami', ['base_url' => 'https://umami.example.com', 'website_id' => 'site-one', 'days' => 3, 'period_days' => [7]]);
        $result = (new UmamiConnector($this->http()))->collect($source, ['username' => 'fixture', 'password' => 'secret-password'], $this->now());
        $this->assertSame(['pageviews' => 10.0, 'visitors' => 8.0, 'visits' => 11.0], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-22'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertCount(2, $result->periods);
        $this->assertSame(123.0, $result->periods[0]['value']);
        $this->assertTrue($result->periods[0]['is_partial']);
        $this->assertFalse($result->periods[1]['is_partial']);
        $this->assertSame('2026-10-19', $result->periods[0]['start_date']);
        $this->assertSame('2026-10-18', $result->periods[1]['end_date']);
        foreach ($result->daily as $row) {
            $this->assertSame($row['date'] === '2026-10-25', $row['is_partial']);
        }
        $this->assertCount(7, $calls);
    }

    public function test_umami_omitted_completed_dates_need_known_creation_and_explicit_valid_counts(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/site-one')) {
                return Http::response(['id' => 'site-one', 'createdAt' => '2026-10-21T12:00:00Z']);
            }
            if (str_ends_with($path, '/pageviews')) {
                return Http::response(['pageviews' => [], 'sessions' => []]);
            }
            $date = CarbonImmutable::createFromTimestampMsUTC($request['startAt'])->toDateString();
            if ($date === '2026-10-23') {
                return Http::response(['pageviews' => 0, 'visitors' => 0, 'visits' => 0]);
            }

            return Http::response(['pageviews' => 0, 'visits' => 0]);
        });
        $result = (new UmamiConnector($this->http()))->collect(new SourceConfiguration('umami', [
            'base_url' => 'https://umami.example.com', 'website_id' => 'site-one', 'days' => 5, 'period_days' => [],
        ]), ['token' => 'synthetic-bearer'], $this->now());
        $this->assertCount(3, $result->daily);
        $this->assertSame(['pageviews' => 0.0, 'visitors' => 0.0, 'visits' => 0.0], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-21'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-25'));
    }

    public function test_umami_retries_one_expired_session_then_fails_without_leaking_password(): void
    {
        $logins = 0;
        Http::fake(function (Request $request) use (&$logins) {
            if (str_ends_with($request->url(), '/login')) {
                $logins++;

                return Http::response(['token' => 'token-'.$logins]);
            }

            return Http::response('secret-password', 401);
        });
        try {
            (new UmamiConnector($this->http()))->collect(new SourceConfiguration('umami', [
                'base_url' => 'https://umami.example.com', 'website_id' => 'site-one', 'period_days' => [],
            ]), ['username' => 'fixture', 'password' => 'secret-password'], $this->now());
            $this->fail('Expected bounded auth failure.');
        } catch (ConnectorException $exception) {
            $this->assertSame(2, $logins);
            $this->assertStringNotContainsString('secret-password', $exception->getMessage());
            Http::assertSentCount(4);
        }
    }

    public function test_umami_passwords_are_sent_verbatim_instead_of_trimmed(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/login')) {
                $this->assertSame("  fixture password\nwith spaces  ", $request['password']);

                return Http::response(['token' => 'fixture-bearer']);
            }
            if (str_contains($request->url(), '/pageviews')) {
                return Http::response(['pageviews' => [], 'sessions' => []]);
            }

            return Http::response(['id' => 'site-one']);
        });
        $result = (new UmamiConnector($this->http()))->collect(new SourceConfiguration('umami', [
            'base_url' => 'https://umami.example.com', 'website_id' => 'site-one', 'period_days' => [],
        ]), ['username' => 'fixture', 'password' => "  fixture password\nwith spaces  "], $this->now());
        $this->assertSame([], $result->daily);
        Http::assertSentCount(3);
    }

    public function test_umami_nonexistent_website_never_yields_empty_success(): void
    {
        Http::fake(['*' => Http::response('null')]);
        $this->expectException(ConnectorException::class);
        try {
            (new UmamiConnector($this->http()))->collect(new SourceConfiguration('umami', [
                'base_url' => 'https://umami.example.com', 'website_id' => 'site-one',
            ]), ['token' => 'synthetic-bearer'], $this->now());
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_google_service_account_uses_signed_exchange_through_the_safe_http_adapter(): void
    {
        $claims = null;
        Http::fake(function (Request $request) use (&$claims) {
            $this->assertSame('https://oauth2.googleapis.com/token', $request->url());
            $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $request['grant_type']);
            [$header, $payload, $signature] = explode('.', $request['assertion']);
            $decode = static fn (string $value): string => base64_decode(strtr($value, '-_', '+/'));
            $claims = json_decode($decode($payload), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(1, openssl_verify($header.'.'.$payload, $decode($signature), self::signingKeys()['public'], OPENSSL_ALGO_SHA256));

            return Http::response(['access_token' => 'google-synthetic-bearer', 'expires_in' => 3600, 'token_type' => 'Bearer']);
        });
        $token = (new GoogleToken($this->http()))->fetch($this->googleCredentials(), 'https://www.googleapis.com/auth/analytics.readonly');
        $this->assertSame('google-synthetic-bearer', $token);
        $this->assertSame('https://www.googleapis.com/auth/analytics.readonly', $claims['scope']);
        $this->assertSame('https://oauth2.googleapis.com/token', $claims['aud']);
        $this->assertSame(3660, $claims['exp'] - $claims['iat']);
    }

    public function test_google_oauth_refresh_tokens_are_supported_without_a_second_http_stack(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('refresh_token', $request['grant_type']);
            $this->assertSame('synthetic-refresh', $request['refresh_token']);
            $this->assertSame('synthetic-client', $request['client_id']);

            return Http::response(['access_token' => 'refreshed-synthetic-token', 'expires_in' => 3600]);
        });
        $token = (new GoogleToken($this->http()))->fetch(['refresh_token' => 'synthetic-refresh', 'client_id' => 'synthetic-client', 'client_secret' => 'synthetic-secret'], 'https://www.googleapis.com/auth/webmasters.readonly');
        $this->assertSame('refreshed-synthetic-token', $token);
    }

    public function test_google_malformed_keys_and_failed_exchanges_never_expose_material(): void
    {
        Http::fake(['*' => Http::response(['error' => 'synthetic-private-material'], 400)]);
        foreach ([['service_account' => '{synthetic-private-material'], $this->googleCredentials(), ['key_file' => '/etc/passwd']] as $credentials) {
            try {
                (new GoogleToken($this->http()))->fetch($credentials, 'https://www.googleapis.com/auth/analytics.readonly');
                $this->fail('Expected Google authentication failure.');
            } catch (ConnectorException $exception) {
                $this->assertStringNotContainsString('synthetic-private-material', $exception->getMessage());
                $this->assertStringNotContainsString('PRIVATE KEY', $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
        }
    }

    public function test_google_expired_or_revoked_authorization_has_its_own_safe_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);
        foreach ([['refresh_token' => 'synthetic-refresh', 'client_id' => 'synthetic-client', 'client_secret' => 'synthetic-secret'], $this->googleCredentials()] as $credentials) {
            try {
                (new GoogleToken($this->http()))->fetch($credentials, 'https://www.googleapis.com/auth/webmasters.readonly');
                $this->fail('Expected an expired Google authorization.');
            } catch (GoogleAuthorizationExpired $exception) {
                $this->assertSame('Google access for this connected account expired or was revoked. Reconnect the account.', $exception->getMessage());
                $this->assertStringNotContainsString('synthetic', $exception->getMessage());
            }
        }
    }

    public function test_ga4_keeps_unknown_dates_partial_tail_and_independent_period_visitors(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/token')) {
                return Http::response(['access_token' => 'synthetic-google-token']);
            }
            $daily = $request['dimensions'] !== [];
            if ($daily) {
                $this->assertSame('2026-10-19', $request['dateRanges'][0]['startDate']);
            }
            $data = $this->ga4Report($daily, $daily ? [
                ['dimensionValues' => [['value' => '20261022']], 'metricValues' => [['value' => '4'], ['value' => '6'], ['value' => '10']]],
                ['dimensionValues' => [['value' => '20261025']], 'metricValues' => [['value' => '0'], ['value' => '0'], ['value' => '0']]],
            ] : [['metricValues' => [['value' => '123']]]]);

            return Http::response($data);
        });
        $result = (new Ga4Connector($this->http()))->collect(new SourceConfiguration('ga4', ['property_id' => '123', 'days' => 1, 'period_days' => [7]]), $this->googleCredentials(), $this->now());
        $this->assertSame(['pageviews' => 10.0, 'visitors' => 4.0, 'visits' => 6.0], $this->valuesOn($result->daily, '2026-10-22'));
        $this->assertSame(['pageviews' => 0.0, 'visitors' => 0.0, 'visits' => 0.0], $this->valuesOn($result->daily, '2026-10-25'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertFalse($result->daily[0]['is_partial']);
        $this->assertTrue($result->daily[3]['is_partial']);
        $this->assertSame(123.0, $result->periods[0]['value']);
        Http::assertSentCount(4);
    }

    public function test_ga4_totals_allow_omitted_empty_dimension_headers(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/token')) {
                return Http::response(['access_token' => 'synthetic-google-token']);
            }
            $daily = $request['dimensions'] !== [];
            $data = $this->ga4Report($daily, $daily ? [] : [['metricValues' => [['value' => '11']]]]);
            if (! $daily) {
                unset($data['dimensionHeaders']);
            }

            return Http::response($data);
        });
        $result = (new Ga4Connector($this->http()))->collect(new SourceConfiguration('ga4', ['property_id' => '123', 'days' => 7, 'period_days' => [7]]), $this->googleCredentials(), $this->now());
        $this->assertSame(11.0, $result->periods[0]['value']);
        $this->assertSame(11.0, $result->periods[1]['value']);
    }

    public function test_ga4_metadata_only_empty_reports_remain_unknown(): void
    {
        Http::fake(['*oauth2*' => Http::response(['access_token' => 'token']),
            '*analyticsdata*' => Http::response(['metadata' => ['timeZone' => 'UTC']])]);
        $result = (new Ga4Connector($this->http()))->collect(new SourceConfiguration('ga4', ['property_id' => 123]), $this->googleCredentials(), $this->now());
        $this->assertSame([], $result->daily);
        $this->assertSame([], $result->periods);
    }

    public static function badGa4Reports(): array
    {
        return [['timezone'], ['sampled'], ['thresholded'], ['truncated'], ['restricted'], ['emptyReason'], ['metricHeaders'], ['dimensionHeaders'], ['duplicateDate'], ['futureDate'], ['negative'], ['missingValue']];
    }

    #[DataProvider('badGa4Reports')]
    public function test_ga4_rejects_untrustworthy_reports(string $problem): void
    {
        $report = $this->ga4Report(true, [['dimensionValues' => [['value' => '20261025']], 'metricValues' => [['value' => '1'], ['value' => '2'], ['value' => '3']]]]);
        switch ($problem) {
            case 'timezone': $report['metadata']['timeZone'] = 'Europe/Sofia';
                break;
            case 'sampled': $report['metadata']['samplingMetadatas'] = [['samplesReadCount' => 1, 'samplingSpaceSize' => 10]];
                break;
            case 'thresholded': $report['metadata']['subjectToThresholding'] = true;
                break;
            case 'truncated': $report['rowCount'] = 2;
                break;
            case 'restricted': $report['metadata']['schemaRestrictionResponse']['activeMetricRestrictions'] = [['metricName' => 'totalUsers']];
                break;
            case 'emptyReason': $report['metadata']['emptyReason'] = 'restricted';
                break;
            case 'metricHeaders': unset($report['metricHeaders']);
                break;
            case 'dimensionHeaders': $report['dimensionHeaders'] = [['name' => 'country']];
                break;
            case 'duplicateDate': $report['rows'][] = $report['rows'][0];
                $report['rowCount'] = 2;
                break;
            case 'futureDate': $report['rows'][0]['dimensionValues'][0]['value'] = '20261026';
                break;
            case 'negative': $report['rows'][0]['metricValues'][0]['value'] = '-1';
                break;
            case 'missingValue': unset($report['rows'][0]['metricValues'][0]['value']);
                break;
        }
        Http::fake(['*oauth2*' => Http::response(['access_token' => 'token']), '*analyticsdata*' => Http::response($report)]);
        $this->expectException(ConnectorException::class);
        (new Ga4Connector($this->http()))->collect(new SourceConfiguration('ga4', ['property_id' => 123, 'period_days' => []]), $this->googleCredentials(), $this->now());
    }

    public function test_ga4_property_ids_are_validated_before_network(): void
    {
        Http::fake();
        try {
            (new Ga4Connector($this->http()))->collect(new SourceConfiguration('ga4', ['property_id' => 'G-123']), $this->googleCredentials(), $this->now());
            $this->fail('Measurement ID was accepted.');
        } catch (ConnectorException) {
            Http::assertNothingSent();
        }
    }

    public function test_ga4_property_timezone_drives_the_window(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/token')) {
                return Http::response(['access_token' => 'token']);
            }
            $this->assertSame('2026-10-24', $request['dateRanges'][0]['endDate']);

            return Http::response(['metadata' => ['timeZone' => 'America/Los_Angeles']]);
        });
        (new Ga4Connector($this->http()))->collect(new SourceConfiguration('ga4', ['property_id' => 123, 'period_days' => []], 'America/Los_Angeles'), $this->googleCredentials(), $this->now()->setTime(1, 0));
    }

    public function test_gsc_uses_pacific_days_encoded_property_and_measured_finality_without_filling_gaps(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/token')) {
                return Http::response(['access_token' => 'token']);
            }
            if (str_ends_with($request->url(), '/sites')) {
                return Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner']]]);
            }
            $this->assertStringContainsString('/sites/sc-domain%3Aexample.com/searchAnalytics/query', $request->url());
            if ($request['dimensions'] === ['query']) {
                return Http::response([]);
            }
            $this->assertSame('2026-10-22', $request['startDate']);
            $this->assertSame('2026-10-24', $request['endDate']);
            if ($request['dataState'] === 'final') {
                return Http::response(['rows' => [['keys' => ['2026-10-22'], 'impressions' => 0, 'clicks' => 0]]]);
            }

            return Http::response(['rows' => [
                ['keys' => ['2026-10-22'], 'impressions' => 0, 'clicks' => 0, 'position' => 0, 'ctr' => 0],
                ['keys' => ['2026-10-24'], 'impressions' => 20, 'clicks' => 2],
            ]]);
        });
        $result = (new GscConnector($this->http()))->collect(new SourceConfiguration('gsc', ['site_url' => 'sc-domain:example.com', 'days' => 1], 'America/Los_Angeles'), $this->googleCredentials(), $this->now()->setTime(1, 0));
        $this->assertCount(4, $result->daily);
        $this->assertSame(['clicks' => 0.0, 'impressions' => 0.0], $this->valuesOn($result->daily, '2026-10-22'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertFalse($result->daily[0]['is_partial']);
        $this->assertTrue($result->daily[2]['is_partial']);
        // Three query windows ending on the latest reported date; no previous window is read when nothing ranked.
        // Four complete months (newest first, up to the last final date) are archived per collection.
        Http::assertSentCount(11);
        $this->assertSame([], $result->queries);
        $this->assertSame(['2026-09-01', '2026-08-01', '2026-07-01', '2026-06-01'], array_column($result->queryMonths, 'month'));
        $this->assertSame([], $result->queryMonths[0]['rows']);
    }

    public function test_gsc_unknown_finality_marks_every_observation_partial(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/token')) {
                return Http::response(['access_token' => 'token']);
            }
            if (str_ends_with($request->url(), '/sites')) {
                return Http::response(['siteEntry' => [['siteUrl' => 'https://example.com/']]]);
            }
            $this->assertStringContainsString('https%3A%2F%2Fexample.com%2F', $request->url());
            if ($request['dimensions'] === ['query']) {
                return Http::response(['rows' => [['keys' => ['example'], 'impressions' => 1, 'clicks' => 0, 'position' => 4.2]]]);
            }

            return Http::response($request['dataState'] === 'final' ? [] : ['rows' => [['keys' => ['2026-10-23'], 'impressions' => 1, 'clicks' => 0]]]);
        });
        $result = (new GscConnector($this->http()))->collect(new SourceConfiguration('gsc', ['site_url' => 'https://example.com/'], 'America/Los_Angeles'), $this->googleCredentials(), $this->now());
        $this->assertTrue($result->daily[0]['is_partial']);
        $this->assertTrue($result->daily[1]['is_partial']);
        $this->assertTrue(collect($result->queries)->every(fn (array $row): bool => $row['is_partial']));
    }

    public function test_gsc_reads_top_queries_for_each_period_and_the_same_queries_before_it(): void
    {
        $requests = [];
        Http::fake(function (Request $request) use (&$requests) {
            if (str_contains($request->url(), '/token')) {
                return Http::response(['access_token' => 'token']);
            }
            if (str_ends_with($request->url(), '/sites')) {
                return Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner']]]);
            }
            if ($request['dimensions'] === ['date']) {
                return Http::response(['rows' => [['keys' => ['2026-10-22'], 'impressions' => 40, 'clicks' => 4]]]);
            }
            if ($request['rowLimit'] === GscConnector::MONTH_LIMIT) {
                return Http::response(['rows' => [['keys' => ['metribay'], 'clicks' => 9, 'impressions' => 90, 'position' => 3.0]]]);
            }
            $requests[] = [$request['startDate'], $request['endDate'], $request['rowLimit'], $request['dataState']];
            $current = $request['endDate'] === '2026-10-22';

            return Http::response(['rows' => $current
                ? [['keys' => ['metribay'], 'clicks' => 3, 'impressions' => 20, 'ctr' => .15, 'position' => 2.5], ['keys' => ['123'], 'clicks' => 0, 'impressions' => 0, 'position' => 0]]
                : [['keys' => ['metribay'], 'clicks' => 1, 'impressions' => 9, 'position' => 6.1], ['keys' => ['other'], 'clicks' => 5, 'impressions' => 50, 'position' => 1.2]]]);
        });
        $result = (new GscConnector($this->http()))->collect(new SourceConfiguration('gsc', ['site_url' => 'sc-domain:example.com', 'days' => 3], 'America/Los_Angeles', null, ['2026-08']), $this->googleCredentials(), $this->now()->setTime(1, 0));

        // Archived months are skipped; four others are read, newest first.
        $this->assertSame(['2026-09-01', '2026-07-01', '2026-06-01', '2026-05-01'], array_column($result->queryMonths, 'month'));
        $this->assertSame(['month' => '2026-09-01', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'weeks' => null,
            'rows' => [['query' => 'metribay', 'clicks' => 9.0, 'impressions' => 90.0, 'position' => 3.0]]], $result->queryMonths[0]);
        $this->assertSame([
            ['2026-10-16', '2026-10-22', 500, 'all'], ['2026-10-09', '2026-10-15', 2000, 'all'],
            ['2026-09-23', '2026-10-22', 500, 'all'], ['2026-08-24', '2026-09-22', 2000, 'all'],
            ['2026-07-25', '2026-10-22', 500, 'all'], ['2026-04-26', '2026-07-24', 2000, 'all'],
        ], $requests);
        $rows = collect($result->queries);
        $this->assertCount(9, $rows);
        $current = $rows->first(fn (array $row): bool => $row['days'] === 30 && ! $row['previous'] && $row['query'] === 'metribay');
        $this->assertSame(['clicks' => 3.0, 'impressions' => 20.0, 'position' => 2.5, 'is_partial' => false], array_intersect_key($current, array_flip(['clicks', 'impressions', 'position', 'is_partial'])));
        // A numeric query stays text; a query without impressions has no position.
        $this->assertNull($rows->first(fn (array $row): bool => $row['query'] === '123')['position']);
        // Only queries of the current window are kept from the window before.
        $this->assertSame(['metribay'], $rows->where('days', 30)->where('previous', true)->pluck('query')->all());
        $this->assertSame(6.1, $rows->first(fn (array $row): bool => $row['days'] === 30 && $row['previous'])['position']);
    }

    public function test_gsc_rejects_duplicate_queries(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/token')) {
                return Http::response(['access_token' => 'token']);
            }
            if (str_ends_with($request->url(), '/sites')) {
                return Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:example.com']]]);
            }

            return Http::response(['rows' => $request['dimensions'] === ['date'] ? [['keys' => ['2026-10-22'], 'impressions' => 1, 'clicks' => 0]]
                : [['keys' => ['same'], 'impressions' => 1, 'clicks' => 0], ['keys' => ['same'], 'impressions' => 2, 'clicks' => 0]]]);
        });
        $this->expectException(ConnectorException::class);
        (new GscConnector($this->http()))->collect(new SourceConfiguration('gsc', ['site_url' => 'sc-domain:example.com'], 'America/Los_Angeles'), $this->googleCredentials(), $this->now());
    }

    public function test_gsc_missing_metric_values_are_unknown_and_rejected(): void
    {
        Http::fake(['*oauth2*' => Http::response(['access_token' => 'token']),
            '*/sites' => Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:example.com']]]),
            '*query' => Http::response(['rows' => [['keys' => ['2026-10-23'], 'impressions' => 1]]])]);
        $this->expectException(ConnectorException::class);
        (new GscConnector($this->http()))->collect(new SourceConfiguration('gsc', ['site_url' => 'sc-domain:example.com'], 'America/Los_Angeles'), $this->googleCredentials(), $this->now());
    }

    /** @return array<string, mixed> */
    private function ga4Report(bool $daily, array $rows): array
    {
        return ['metadata' => ['timeZone' => 'UTC'], 'rowCount' => count($rows), 'rows' => $rows,
            'dimensionHeaders' => $daily ? [['name' => 'date']] : [],
            'metricHeaders' => array_map(static fn (string $name): array => ['name' => $name], $daily ? ['totalUsers', 'sessions', 'screenPageViews'] : ['totalUsers'])];
    }
}
