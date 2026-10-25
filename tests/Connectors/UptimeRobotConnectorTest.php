<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Connectors\UptimeRobotConnector;
use Metribay\Core\Connectors\UptimeRobotRateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__.'/FixtureTestCase.php';

final class UptimeRobotConnectorTest extends FixtureTestCase
{
    public function test_results_are_present_day_observations_with_precision_and_an_atomic_replacement_set(): void
    {
        $this->uptimeFake();
        $result = $this->collect();
        $this->assertSame(['monitor_status' => 2.0, 'response_time_24h' => 123.4, 'uptime_24h' => 99.987], $this->valuesOn($result->daily, '2026-10-25'));
        $this->assertSame([], $result->periods);
        $this->assertSame(['2026-10-25' => ['monitor_status', 'uptime_24h', 'response_time_24h']], $result->replaceDailyDates);
        $this->assertCount(3, $result->daily);
        Http::assertSentCount(3);
    }

    public static function monitorStates(): array
    {
        return [['PAUSED', 0], ['STARTED', 1], ['NOT_CHECKED_YET', 1], ['UP', 2], ['LOOKS_DOWN', 8], ['SEEMS_DOWN', 8], ['DOWN', 9], [8, 8]];
    }

    #[DataProvider('monitorStates')]
    public function test_status_codes_are_preserved_and_paused_or_uninitialised_monitors_do_not_claim_uptime(string|int $status, int $expected): void
    {
        $this->uptimeFake(status: $status, average: null, points: 0);
        $values = $this->valuesOn($this->collect()->daily, '2026-10-25');
        $this->assertSame((float) $expected, $values['monitor_status']);
        $this->assertSame(! in_array($expected, [0, 1], true), array_key_exists('uptime_24h', $values));
        $this->assertArrayNotHasKey('response_time_24h', $values);
    }

    public function test_heartbeats_do_not_request_http_latency(): void
    {
        $this->uptimeFake(type: 'HEARTBEAT');
        $values = $this->valuesOn($this->collect()->daily, '2026-10-25');
        $this->assertArrayNotHasKey('response_time_24h', $values);
        $this->assertArrayHasKey('uptime_24h', $values);
        Http::assertSentCount(2);
    }

    public function test_a_later_paused_or_sample_free_snapshot_replaces_earlier_observations(): void
    {
        $this->uptimeFake(status: 'PAUSED', average: null, points: 0);
        $result = $this->collect();
        $this->assertSame(['monitor_status' => 0.0], $this->valuesOn($result->daily, '2026-10-25'));
        $this->assertContains('uptime_24h', $result->replaceDailyDates['2026-10-25']);
        $this->assertContains('response_time_24h', $result->replaceDailyDates['2026-10-25']);
    }

    public static function invalidResponses(): array
    {
        return [['http'], ['rateLimit'], ['json'], ['identity'], ['status'], ['window'], ['ratio'], ['samples'], ['missingAverage'], ['unsampledAverage'], ['timeout']];
    }

    #[DataProvider('invalidResponses')]
    public function test_invalid_data_is_rejected_without_monitor_credentials_or_provider_text(string $problem): void
    {
        $this->uptimeFake(override: function (Request $request, array $normal) use ($problem) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($problem === 'timeout') {
                throw new \RuntimeException('synthetic-secret-monitor-password');
            }
            if ($problem === 'http') {
                return Http::response('synthetic-secret-monitor-password', 401);
            }
            if ($problem === 'rateLimit') {
                return Http::response('synthetic-secret-monitor-password', 429);
            }
            if ($problem === 'json') {
                return Http::response('synthetic-secret-monitor-password');
            }
            if (str_ends_with($path, '/123')) {
                if ($problem === 'identity') {
                    $normal['id'] = 999;
                } elseif ($problem === 'status') {
                    $normal['status'] = 'UNKNOWN';
                }
            } elseif (str_ends_with($path, '/uptime')) {
                if ($problem === 'window') {
                    $normal['from'] = '2026-01-01T00:00:00Z';
                } elseif ($problem === 'ratio') {
                    $normal['uptime'] = 101;
                }
            } else {
                if ($problem === 'samples') {
                    $normal['data_points'] = -1;
                } elseif ($problem === 'missingAverage') {
                    $normal['summary']['avg'] = null;
                } elseif ($problem === 'unsampledAverage') {
                    $normal['data_points'] = 0;
                }
            }

            return Http::response($normal);
        });
        try {
            $this->collect();
            $this->fail('Invalid UptimeRobot data was accepted.');
        } catch (ConnectorException $exception) {
            $this->assertStringNotContainsString('synthetic-secret', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_rate_limiter_spaces_requests_across_instances_sharing_an_account_but_not_different_accounts(): void
    {
        Cache::flush();
        $clock = 1000.0;
        $sleeps = [];
        $now = static function () use (&$clock): float {
            return $clock;
        };
        $sleep = static function (int $delay) use (&$clock, &$sleeps): void {
            $sleeps[] = $delay;
            $clock += $delay / 1_000_000;
        };
        $one = new UptimeRobotRateLimiter($now, $sleep);
        $two = new UptimeRobotRateLimiter($now, $sleep);
        $one->wait('shared-synthetic-account');
        $clock += 1;
        $two->wait('shared-synthetic-account');
        $one->wait('different-synthetic-account');
        $two->wait('shared-synthetic-account');
        $this->assertCount(2, $sleeps);
        $this->assertEqualsWithDelta(5_100_000, $sleeps[0], 1);
        $this->assertEqualsWithDelta(6_100_000, $sleeps[1], 1);
    }

    public function test_invalid_monitor_configuration_fails_before_network(): void
    {
        Http::fake();
        $this->expectException(ConnectorException::class);
        try {
            (new UptimeRobotConnector($this->http(), $this->limiter()))->collect(new SourceConfiguration('uptimerobot', ['monitor_id' => '../123']), ['api_key' => 'synthetic-uptime-key'], $this->now());
        } finally {
            Http::assertNothingSent();
        }
    }

    private function collect(): CollectionResult
    {
        return (new UptimeRobotConnector($this->http(), $this->limiter()))->collect(new SourceConfiguration('uptimerobot', ['monitor_id' => 123]), ['api_key' => 'synthetic-uptime-key'], $this->now());
    }

    private function limiter(): UptimeRobotRateLimiter
    {
        $clock = 1000.0;

        return new UptimeRobotRateLimiter(static function () use (&$clock): float {
            return $clock;
        },
            static function (int $delay) use (&$clock): void {
                $clock += $delay / 1_000_000;
            });
    }

    private function uptimeFake(string|int $status = 'UP', string $type = 'HTTP', ?float $average = 123.4, int $points = 12, ?callable $override = null): void
    {
        Http::fake(function (Request $request) use ($status, $type, $average, $points, $override) {
            $this->assertSame('Bearer synthetic-uptime-key', $request->header('Authorization')[0]);
            $this->assertStringNotContainsString('synthetic-uptime-key', $request->url());
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/123')) {
                $data = ['id' => 123, 'type' => $type, 'status' => $status, 'httpPassword' => 'synthetic-secret-monitor-password'];
            } else {
                $this->assertSame('2026-10-24T10:00:00+00:00', $request['from']);
                $this->assertSame('2026-10-25T10:00:00+00:00', $request['to']);
                $data = ['from' => $request['from'], 'to' => $request['to']];
                $data += str_ends_with($path, '/uptime') ? ['uptime' => 99.987]
                    : ['summary' => ['avg' => $average, 'min' => 10, 'max' => 200], 'data_points' => $points];
            }
            if ($override !== null) {
                return $override($request, $data);
            }

            return Http::response($data);
        });
    }
}
