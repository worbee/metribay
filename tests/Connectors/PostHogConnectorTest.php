<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\DiscoveryException;
use Metribay\Core\Connectors\PostHogConnector;
use Metribay\Core\Connectors\ResourceDiscovery;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Services\ProjectResources;
use Metribay\Core\Services\ProviderCatalog;

require_once __DIR__.'/FixtureTestCase.php';

final class PostHogConnectorTest extends FixtureTestCase
{
    private const CREDENTIALS = ['base_url' => 'https://posthog.example.com', 'api_key' => 'phx_synthetic'];

    private const USAGE_COLUMNS = ['day', 'visitors', 'visits', 'pageviews', 'active_users'];

    public function test_usage_maps_daily_rows_fills_quiet_days_with_zero_and_counts_people_once_per_window(): void
    {
        $queries = [];
        Http::fake(function (Request $request) use (&$queries) {
            $this->assertStringNotContainsString('phx_synthetic', $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer phx_synthetic'));
            if ($request->url() === 'https://posthog.example.com/api/projects/12345/') {
                return Http::response(['id' => 12345, 'name' => 'Synthetic app', 'timezone' => 'Europe/Berlin', 'created_at' => '2026-10-20T08:00:00Z']);
            }
            $this->assertSame('https://posthog.example.com/api/projects/12345/query/', $request->url());
            $query = $request->data()['query'];
            $this->assertSame('HogQLQuery', $query['kind']);
            // Dates and the timezone are placeholders, never part of the query text.
            $this->assertStringNotContainsString('2026-', $query['query']);
            $this->assertStringNotContainsString('Europe/Berlin', $query['query']);
            $this->assertSame('Europe/Berlin', $query['values']['tz']);
            $queries[] = $query;
            if (str_contains($query['query'], 'GROUP BY day')) {
                $this->assertSame(['from' => '2026-10-22 00:00:00', 'to' => '2026-10-26 00:00:00', 'tz' => 'Europe/Berlin'], $query['values']);

                return Http::response(['columns' => self::USAGE_COLUMNS, 'results' => [
                    ['2026-10-22', 4, 5, 9, 6],
                    ['2026-10-25', 1, 1, 2, 2],
                ], 'hasMore' => false]);
            }
            $this->assertSame(['2026-10-23 00:00:00', '2026-10-26 00:00:00', '2026-10-20 00:00:00', '2026-10-23 00:00:00'],
                [$query['values']['from0'], $query['values']['to0'], $query['values']['from1'], $query['values']['to1']]);

            return Http::response(['columns' => ['visitors_0', 'active_users_0', 'visitors_1', 'active_users_1'], 'results' => [[3, 4, 7, 8]]]);
        });
        $result = (new PostHogConnector($this->http()))->collect($this->usage(), self::CREDENTIALS, $this->now());

        $this->assertSame(['active_users' => 6.0, 'pageviews' => 9.0, 'visitors' => 4.0, 'visits' => 5.0], $this->valuesOn($result->daily, '2026-10-22'));
        $this->assertSame(['active_users' => 0.0, 'pageviews' => 0.0, 'visitors' => 0.0, 'visits' => 0.0], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame(['active_users' => 0.0, 'pageviews' => 0.0, 'visitors' => 0.0, 'visits' => 0.0], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame(['active_users' => 2.0, 'pageviews' => 2.0, 'visitors' => 1.0, 'visits' => 1.0], $this->valuesOn($result->daily, '2026-10-25'));
        $this->assertFalse($result->daily[0]['is_partial']);
        $this->assertTrue($result->daily[array_key_last($result->daily)]['is_partial']);
        $this->assertSame([
            ['metric' => 'visitors', 'start_date' => '2026-10-23', 'end_date' => '2026-10-25', 'value' => 3.0, 'is_partial' => true],
            ['metric' => 'active_users', 'start_date' => '2026-10-23', 'end_date' => '2026-10-25', 'value' => 4.0, 'is_partial' => true],
            ['metric' => 'visitors', 'start_date' => '2026-10-20', 'end_date' => '2026-10-22', 'value' => 7.0, 'is_partial' => false],
            ['metric' => 'active_users', 'start_date' => '2026-10-20', 'end_date' => '2026-10-22', 'value' => 8.0, 'is_partial' => false],
        ], $result->periods);
        $this->assertCount(2, $queries, 'Daily rows and every period total take one query each.');
    }

    public function test_days_before_the_project_existed_stay_unknown(): void
    {
        $this->fakeProject(['created_at' => '2026-10-23T12:00:00Z'], fn (array $query) => str_contains($query['query'], 'GROUP BY day')
            ? Http::response(['columns' => self::USAGE_COLUMNS, 'results' => []])
            : Http::response(['columns' => ['visitors_0', 'active_users_0', 'visitors_1', 'active_users_1'], 'results' => [[0, 0, 0, 0]]]));
        $result = (new PostHogConnector($this->http()))->collect($this->usage(), self::CREDENTIALS, $this->now());

        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-22'));
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-23'), 'The creation day may be incomplete.');
        $this->assertSame(0.0, $this->valuesOn($result->daily, '2026-10-24')['visits']);
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-25'), 'Today without events is not yet a zero.');
    }

    public function test_a_key_event_source_counts_one_event_passed_as_a_value(): void
    {
        $event = "plan'upgraded";
        $sent = [];
        Http::fake(function (Request $request) use ($event, &$sent) {
            $sent[] = $request->url();
            if (str_contains($request->url(), '/event_definitions/')) {
                $this->assertSame($event, $request->data()['search']);

                return Http::response(['results' => [['name' => $event.'_v2'], ['name' => $event]]]);
            }
            if (str_ends_with($request->url(), '/api/projects/12345/')) {
                return Http::response(['id' => 12345, 'timezone' => 'UTC', 'created_at' => '2026-01-01T00:00:00Z']);
            }
            $query = $request->data()['query'];
            $this->assertStringNotContainsString($event, $query['query']);
            $this->assertSame($event, $query['values']['event']);

            return Http::response(['columns' => ['day', 'key_events'], 'results' => [['2026-10-23', 12]]]);
        });
        $result = (new PostHogConnector($this->http()))->collect(new SourceConfiguration('posthog', ['project_id' => '12345', 'event' => $event, 'days' => 3]), self::CREDENTIALS, $this->now());

        $this->assertSame(['key_events'], array_values(array_unique(array_column($result->daily, 'metric'))));
        $this->assertSame(['key_events' => 12.0], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertSame(['key_events' => 0.0], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame([], $result->periods);
        $this->assertCount(3, $sent, 'A key event needs no period query.');
    }

    public function test_an_event_posthog_has_not_seen_fails_instead_of_counting_zeros(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/event_definitions/')) {
                return Http::response(['results' => [['name' => 'user_signed_up_v2']]]);
            }

            return Http::response(['id' => 12345, 'timezone' => 'UTC']);
        });
        try {
            (new PostHogConnector($this->http()))->collect(new SourceConfiguration('posthog', ['project_id' => '12345', 'event' => 'user_signed_up', 'days' => 3]), self::CREDENTIALS, $this->now());
            $this->fail('An unknown event must not collect as zeros.');
        } catch (ConnectorException $exception) {
            $this->assertStringContainsString('event name', $exception->getMessage());
        }
        Http::assertSentCount(2);
    }

    public function test_a_different_project_timezone_stops_before_any_query(): void
    {
        Http::fake(['posthog.example.com/api/projects/12345/' => Http::response(['id' => 12345, 'timezone' => 'America/New_York'])]);
        try {
            (new PostHogConnector($this->http()))->collect($this->usage(), self::CREDENTIALS, $this->now());
            $this->fail('A different project timezone must stop collection.');
        } catch (ConnectorException $exception) {
            $this->assertStringContainsString('timezone', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_access_errors_and_unexpected_answers_fail_without_leaking_the_response(): void
    {
        $cases = [
            'missing project' => [fn () => Http::response(['detail' => 'provider-secret'], 404), 'not visible'],
            'rejected key' => [fn () => Http::response(['detail' => 'provider-secret'], 401), 'personal API key'],
            'rate limit' => [fn () => Http::response(['detail' => 'provider-secret'], 429), 'rate limiting'],
            'other project' => [fn () => Http::response(['id' => 999, 'timezone' => 'Europe/Berlin']), 'different project'],
        ];
        $current = null;
        Http::fake(function (Request $request) use (&$current) {
            return $current($request);
        });
        foreach ($cases as $name => [$response, $message]) {
            $current = $response;
            $this->assertCollectionFails($name, $message);
        }
        $answers = [
            'rewritten columns' => ['columns' => ['day', 'visitors'], 'results' => []],
            'truncated' => ['columns' => self::USAGE_COLUMNS, 'results' => [], 'hasMore' => true],
            'out of window' => ['columns' => self::USAGE_COLUMNS, 'results' => [['2026-10-26', 1, 1, 1, 1]]],
            'duplicate day' => ['columns' => self::USAGE_COLUMNS, 'results' => [['2026-10-23', 1, 1, 1, 1], ['2026-10-23', 1, 1, 1, 1]]],
            'fraction' => ['columns' => self::USAGE_COLUMNS, 'results' => [['2026-10-23', 1.5, 1, 1, 1]]],
            'query error' => null,
        ];
        foreach ($answers as $name => $answer) {
            $current = fn (Request $request) => str_ends_with($request->url(), '/api/projects/12345/') ? Http::response(['id' => 12345, 'timezone' => 'Europe/Berlin'])
                : ($answer === null ? Http::response(['type' => 'validation_error', 'detail' => 'provider-secret'], 400) : Http::response($answer));
            $this->assertCollectionFails($name, 'PostHog');
        }
    }

    public function test_discovery_lists_projects_by_page_and_reads_one_project_timezone(): void
    {
        Http::fake(function (Request $request) {
            $this->assertStringNotContainsString('phx_synthetic', $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer phx_synthetic'));
            if (str_ends_with($request->url(), '/api/projects/7/')) {
                return Http::response(['id' => 7, 'timezone' => 'Europe/Ljubljana']);
            }
            $this->assertSame('100', (string) $request->data()['limit']);
            if ((string) $request->data()['offset'] === '0') {
                return Http::response(['results' => array_map(fn (int $id): array => ['id' => $id, 'name' => 'Project '.$id], range(1, 100)), 'next' => 'https://posthog.example.com/api/projects/?offset=100']);
            }

            return Http::response(['results' => [['id' => 101, 'name' => ' ', 'timezone' => 'Europe/Berlin']], 'next' => null]);
        });
        $discovery = new ResourceDiscovery($this->http());
        $rows = $discovery->discover('posthog', self::CREDENTIALS);
        $this->assertCount(101, $rows);
        $this->assertSame(['id' => '1', 'name' => 'Project 1', 'domain' => '', 'timezone' => ''], $rows[0]);
        $this->assertSame(['id' => '101', 'name' => '101', 'domain' => '', 'timezone' => 'Europe/Berlin'], $rows[100]);
        $this->assertSame('Europe/Ljubljana', $discovery->postHogTimezone(self::CREDENTIALS, '7'));
    }

    public function test_discovery_failures_are_actionable_and_hide_the_body(): void
    {
        Http::fake(['posthog.example.com/*' => Http::response(['detail' => 'phx_synthetic provider-secret'], 403)]);
        try {
            (new ResourceDiscovery($this->http()))->discover('posthog', self::CREDENTIALS);
            $this->fail('A rejected key must not list projects.');
        } catch (DiscoveryException $exception) {
            $this->assertStringContainsString('personal API key', $exception->getMessage());
            $this->assertStringNotContainsString('provider-secret', $exception->getMessage());
        }
    }

    public function test_catalog_normalizes_hosts_rejects_project_keys_and_splits_usage_from_events(): void
    {
        $this->assertSame(['base_url' => 'https://eu.posthog.com', 'api_key' => 'phx_synthetic'],
            ProviderCatalog::credentials('posthog', ['base_url' => 'https://eu.i.posthog.com/', 'api_key' => ' phx_synthetic ']));
        $this->assertSame('https://us.posthog.com', ProviderCatalog::credentials('posthog', ['base_url' => 'https://app.posthog.com', 'api_key' => 'phx_synthetic'])['base_url']);
        $this->assertSame('https://posthog.example.com/analytics', ProviderCatalog::credentials('posthog', ['base_url' => 'https://PostHog.example.com/analytics/', 'api_key' => 'phx_synthetic'])['base_url']);
        foreach ([['base_url' => 'https://us.posthog.com', 'api_key' => 'phc_ingestion_only'], ['base_url' => 'http://posthog.example.com', 'api_key' => 'phx_synthetic']] as $values) {
            try {
                ProviderCatalog::credentials('posthog', $values);
                $this->fail('Unusable PostHog credentials must be rejected.');
            } catch (ValidationException) {
            }
        }

        $usage = ProviderCatalog::source('posthog', ['project_id' => '12345', 'event' => '  '], 'Europe/Berlin');
        $event = ProviderCatalog::source('posthog', ['project_id' => '12345', 'event' => ' user_signed_up '], 'Europe/Berlin');
        $this->assertSame(['project_id' => '12345', 'days' => 180], $usage);
        $this->assertSame(['project_id' => '12345', 'event' => 'user_signed_up', 'days' => 180], $event);
        $this->assertSame(['visitors', 'visits', 'pageviews', 'active_users'], ProviderCatalog::metricKeys('posthog', $usage));
        $this->assertSame(['key_events'], ProviderCatalog::metricKeys('posthog', $event));
        $this->assertSame(['visitors', 'visits', 'pageviews', 'active_users', 'key_events'], ProviderCatalog::metricKeys('posthog'));
        $this->assertTrue(ProviderCatalog::usesSiteTimezone('posthog'));
        $this->assertNotSame(ProjectResources::resource('posthog', $usage), ProjectResources::resource('posthog', $event));
        $this->assertNotSame(ProviderCatalog::identityHash('posthog', $usage, 'Europe/Berlin'), ProviderCatalog::identityHash('posthog', $event, 'Europe/Berlin'));
        foreach ([['project_id' => '0'], ['project_id' => 'abc'], ['project_id' => '12345', 'event' => "bad\nname"]] as $config) {
            try {
                ProviderCatalog::source('posthog', $config, 'UTC');
                $this->fail('An invalid PostHog source must be rejected.');
            } catch (ValidationException) {
            }
        }
    }

    private function usage(): SourceConfiguration
    {
        return new SourceConfiguration('posthog', ['project_id' => '12345', 'days' => 4, 'period_days' => [3]], 'Europe/Berlin');
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  \Closure(array<string, mixed>): mixed  $query
     */
    private function fakeProject(array $project, \Closure $query): void
    {
        Http::fake(function (Request $request) use ($project, $query) {
            if (str_ends_with($request->url(), '/api/projects/12345/')) {
                return Http::response($project + ['id' => 12345, 'timezone' => 'Europe/Berlin']);
            }

            return $query($request->data()['query']);
        });
    }

    private function assertCollectionFails(string $case, string $message): void
    {
        try {
            (new PostHogConnector($this->http()))->collect($this->usage(), self::CREDENTIALS, $this->now());
            $this->fail($case.' must fail the collection.');
        } catch (ConnectorException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage(), $case);
            $this->assertStringNotContainsString('provider-secret', $exception->getMessage(), $case);
        }
    }
}
