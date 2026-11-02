<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\PageSpeedConnector;
use Metribay\Core\Connectors\SourceConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__.'/FixtureTestCase.php';

final class PageSpeedConnectorTest extends FixtureTestCase
{
    private function lab(string $strategy = 'mobile'): array
    {
        return ['lighthouseResult' => ['requestedUrl' => 'https://example.com/', 'finalUrl' => 'https://example.com/',
            'lighthouseVersion' => '13.0.0', 'fetchTime' => '2026-10-25T09:59:00Z', 'configSettings' => ['formFactor' => $strategy],
            'categories' => ['performance' => ['score' => 0.91]], 'audits' => ['largest-contentful-paint' => ['numericValue' => 2100.5],
                'cumulative-layout-shift' => ['numericValue' => 0.025], 'total-blocking-time' => ['numericValue' => 0]]]];
    }

    private function field(bool $history = false, string $scope = 'url', string $strategy = 'mobile'): array
    {
        $record = ['key' => [$scope => $scope === 'origin' ? 'https://example.com' : 'https://example.com/', 'formFactor' => $strategy === 'mobile' ? 'PHONE' : 'DESKTOP']];
        $period = ['firstDate' => ['year' => 2026, 'month' => 9, 'day' => 26], 'lastDate' => ['year' => 2026, 'month' => 10, 'day' => 23]];
        if ($history) {
            $record['collectionPeriods'] = [
                ['firstDate' => ['year' => 2026, 'month' => 9, 'day' => 19], 'lastDate' => ['year' => 2026, 'month' => 10, 'day' => 16]], $period];
        } else {
            $record['collectionPeriod'] = $period;
        }
        foreach (['largest_contentful_paint' => 1800, 'interaction_to_next_paint' => 150, 'cumulative_layout_shift' => '0.03'] as $metric => $value) {
            $record['metrics'][$metric] = $history ? ['percentilesTimeseries' => ['p75s' => [null, $value]]] : ['percentiles' => ['p75' => $value]];
        }

        return ['record' => $record];
    }

    private function collect(string $strategy = 'mobile'): CollectionResult
    {
        return (new PageSpeedConnector($this->http()))->collect(new SourceConfiguration('pagespeed', ['url' => 'https://example.com/', 'strategy' => $strategy], 'UTC'), ['api_key' => 'synthetic-google-key'], $this->now());
    }

    public function test_lab_and_field_observations_preserve_scope_device_precision_windows_and_missing_history(): void
    {
        Http::fake(fn (Request $r) => Http::response(str_contains($r->url(), 'pagespeedonline') ? $this->lab() : $this->field(str_contains($r->url(), 'queryHistory'))));
        $result = $this->collect();
        $this->assertSame(['lab_cls' => 0.025, 'lab_lcp' => 2100.5, 'lab_score' => 91.0, 'lab_tbt' => 0.0], $this->valuesOn($result->daily, '2026-10-25'));
        $this->assertSame(['crux_cls' => 0.03, 'crux_inp' => 150.0, 'crux_lcp' => 1800.0], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-16'));
        $this->assertSame('2026-09-26T00:00:00+00:00', $result->daily[4]['window_start']);
        $this->assertSame('url', $result->daily[4]['context']['scope']);
        $this->assertSame('mobile', $result->daily[4]['context']['device']);
        $this->assertSame('13.0.0', $result->daily[0]['context']['lighthouse_version']);
        $this->assertStringNotContainsString('synthetic-google-key', json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertSame(['lab_score', 'lab_lcp', 'lab_cls', 'lab_tbt'], $result->replaceDailyDates['2026-10-25']);
        $this->assertSame(['crux_lcp', 'crux_inp', 'crux_cls'], $result->replaceDailyDates['2026-10-16']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'queryHistory') && $r['collectionPeriodCount'] === 40);
        Http::assertSentCount(3);
        $this->collect();
        Http::assertSentCount(3);
    }

    public function test_missing_page_data_falls_back_to_distinct_origin_series(): void
    {
        Http::fake(fn (Request $r) => Http::response(str_contains($r->url(), 'pagespeedonline') ? $this->lab() : (isset($r['origin']) ? $this->field(str_contains($r->url(), 'queryHistory'), 'origin') : ['error' => ['status' => 'NOT_FOUND']]), isset($r['url']) && str_contains($r->url(), 'chromeux') ? 404 : 200));
        $result = $this->collect();
        $this->assertArrayHasKey('crux_origin_lcp', $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertArrayNotHasKey('crux_lcp', $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame('origin', $result->daily[4]['context']['scope']);
        Http::assertSentCount(5);
    }

    public function test_low_traffic_is_unknown_and_does_not_fail_a_lab_test(): void
    {
        Http::fake(['*pagespeedonline*' => Http::response($this->lab()), '*chromeux*' => Http::response(['error' => ['status' => 'NOT_FOUND']], 404)]);
        $result = $this->collect();
        $this->assertCount(4, $result->daily);
        $this->assertSame('no_data', $result->daily[0]['context']['field_status']);
        Http::assertSentCount(5);
    }

    public function test_historical_page_data_is_imported_even_without_current_page_or_origin_coverage(): void
    {
        Http::fake(fn (Request $r) => str_contains($r->url(), 'pagespeedonline') ? Http::response($this->lab())
            : (str_contains($r->url(), 'queryHistory') && isset($r['url']) ? Http::response($this->field(true)) : Http::response(['error' => ['status' => 'NOT_FOUND']], 404)));
        $result = $this->collect();
        $this->assertSame('no_data', $result->daily[0]['context']['field_status']);
        $this->assertArrayHasKey('crux_lcp', $this->valuesOn($result->daily, '2026-10-23'));
        Http::assertSentCount(5);
    }

    public function test_crux_permission_or_service_failure_keeps_lab_and_retries_without_another_lab_request(): void
    {
        Http::fake(['*pagespeedonline*' => Http::response($this->lab()), '*chromeux*' => Http::response(['error' => ['message' => 'synthetic-google-key']], 403)]);
        $result = $this->collect();
        $this->assertSame('unavailable', $result->daily[0]['context']['field_status']);
        $this->assertCount(4, $result->daily);
        $this->collect();
        Http::assertSentCount(3);
    }

    public function test_history_failure_does_not_discard_current_field_measurements(): void
    {
        Http::fake(['*pagespeedonline*' => Http::response($this->lab()), '*queryHistory*' => Http::response([], 500), '*queryRecord*' => Http::response($this->field())]);
        $result = $this->collect();
        $this->assertSame('unavailable', $result->daily[0]['context']['history_status']);
        $this->assertSame('available', $result->daily[0]['context']['field_status']);
        $this->assertCount(7, $result->daily);
    }

    public static function invalidLabs(): array
    {
        return [['score'], ['url'], ['device'], ['time'], ['runtime'], ['http'], ['exception']];
    }

    #[DataProvider('invalidLabs')]
    public function test_bad_lab_results_fail_safely_without_saving_partial_or_zero_metrics(string $problem): void
    {
        Http::fake(function () use ($problem) {
            $data = $this->lab();
            match ($problem) {
                'score' => $data['lighthouseResult']['categories']['performance']['score'] = null,
                'url' => $data['lighthouseResult']['requestedUrl'] = 'https://other.example.com/',
                'device' => $data['lighthouseResult']['configSettings']['formFactor'] = 'desktop',
                'time' => $data['lighthouseResult']['fetchTime'] = '2026-12-25T10:00:00Z',
                'runtime' => $data['lighthouseResult']['runtimeError'] = ['message' => 'synthetic-google-key'],
                'exception' => throw new \RuntimeException('synthetic-google-key'),
                default => null,
            };

            return Http::response($data, $problem === 'http' ? 429 : 200);
        });
        try {
            $this->collect();
            $this->fail('Expected a safe connector failure');
        } catch (ConnectorException $exception) {
            $this->assertStringNotContainsString('synthetic-google-key', $exception->getMessage());
        }
        Http::assertSentCount($problem === 'exception' ? 0 : 1);
    }

    public static function unsafeUrls(): array
    {
        return [['http://example.com/'], ['https://localhost/'], ['https://127.0.0.1/'], ['https://x.internal/'], ['https://user:password@example.com/'], ['https://example.com/#token']];
    }

    #[DataProvider('unsafeUrls')]
    public function test_invalid_page_urls_are_rejected_before_any_provider_request(string $url): void
    {
        Http::fake();
        try {
            (new PageSpeedConnector($this->http()))->collect(new SourceConfiguration('pagespeed', ['url' => $url]), ['api_key' => 'synthetic-google-key'], $this->now());
            $this->fail('Expected URL rejection');
        } catch (ConnectorException) {
            Http::assertNothingSent();
        }
    }
}
