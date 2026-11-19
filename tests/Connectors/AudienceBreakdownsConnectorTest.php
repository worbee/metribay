<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\Breakdowns;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\Ga4Connector;
use Metribay\Core\Connectors\MatomoConnector;
use Metribay\Core\Connectors\PlausibleConnector;
use Metribay\Core\Connectors\PostHogConnector;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Connectors\UmamiConnector;

require_once __DIR__.'/FixtureTestCase.php';

final class AudienceBreakdownsConnectorTest extends FixtureTestCase
{
    public function test_provider_channel_device_and_country_names_map_to_one_list(): void
    {
        foreach (['Organic Search' => 'organic_search', 'organicSearch' => 'organic_search', 'Search Engines' => 'organic_search', 'Direct' => 'direct', 'Paid Social' => 'paid_social',
            'AI Assistant' => 'ai_assistant', 'llm' => 'ai_assistant', 'Cross-network' => 'other_paid', 'paidAds' => 'other_paid', 'Websites' => 'referral', 'Organic Shopping' => 'shopping',
            'Campaigns' => 'campaign', 'Unassigned' => 'other', '(other)' => 'other'] as $name => $channel) {
            $this->assertSame($channel, Breakdowns::channel($name), $name);
        }
        $this->assertSame(['desktop', 'desktop', 'mobile', 'mobile', 'tablet', 'other'], array_map(Breakdowns::device(...), ['Desktop', 'laptop', 'smartphone', 'Phablet', 'Tablet', 'smart tv']));
        $this->assertSame(['US', 'unknown', 'unknown'], array_map(Breakdowns::country(...), ['us', 'XX', '(not set)']));
        $this->assertSame(array_values(array_unique(Breakdowns::CHANNELS)), Breakdowns::CHANNELS);
    }

    public function test_collection_merges_ranks_and_keeps_a_failing_dimension_apart(): void
    {
        $today = CarbonImmutable::parse('2026-10-25', 'UTC');
        $result = Breakdowns::collect([
            'device' => Breakdowns::perWindow(fn (CarbonImmutable $from, CarbonImmutable $end): array => [
                ['item' => 'Laptop', 'visits' => 2.0, 'visitors' => 2.0], ['item' => 'Desktop', 'visits' => 5.0, 'visitors' => 4.0], ['item' => 'Mobile', 'visits' => 6.0, 'visitors' => 5.0], ['item' => 'Tablet', 'visits' => 0.0]]),
            'referrer' => Breakdowns::perWindow(fn (): array => throw new ConnectorException('Synthetic provider failure.')),
        ], $today);
        $this->assertSame(['device'], $result['dimensions']);
        $this->assertSame(['referrer' => 'Synthetic provider failure.'], $result['errors']);
        $current = array_values(array_filter($result['rows'], fn (array $row): bool => $row['days'] === 7 && ! $row['previous']));
        // Laptops join desktops: visits add up, visitors would count people twice and become unknown. Empty items are dropped.
        $this->assertSame([['item' => 'desktop', 'visits' => 7.0, 'visitors' => null], ['item' => 'mobile', 'visits' => 6.0, 'visitors' => 5.0]],
            array_map(fn (array $row): array => array_intersect_key($row, array_flip(['item', 'visits', 'visitors'])), $current));
        $this->assertSame(['2026-10-19', '2026-10-25', true], [$current[0]['start_date'], $current[0]['end_date'], $current[0]['is_partial']]);
        $previous = array_values(array_filter($result['rows'], fn (array $row): bool => $row['days'] === 90 && $row['previous']));
        $this->assertSame(['2026-04-29', '2026-07-27', false], [$previous[0]['start_date'], $previous[0]['end_date'], $previous[0]['is_partial']]);
    }

    public function test_ga4_reads_engagement_key_events_channel_days_and_top_lists(): void
    {
        $reports = [];
        Http::fake(function (Request $request) use (&$reports) {
            if (str_contains($request->url(), '/token')) {
                return Http::response(['access_token' => 'synthetic-google-token']);
            }
            $dimensions = array_column($request['dimensions'], 'name');
            $metrics = array_column($request['metrics'], 'name');
            $reports[] = $dimensions;
            $rows = match ($dimensions) {
                ['date'] => [$this->ga4Row(['20261024'], [3, 4, 8, 3, 20, 2]), $this->ga4Row(['20261025'], [1, 1, 2, 0, 0, 0])],
                [] => [$this->ga4Row([], [3])],
                ['date', 'sessionDefaultChannelGroup'] => [$this->ga4Row(['20261024', 'Organic Search'], [3]), $this->ga4Row(['20261024', 'Organic Shopping'], [1]), $this->ga4Row(['20261025', 'Direct'], [1])],
                ['sessionSource'] => [$this->ga4Row(['google'], [3, 2]), $this->ga4Row(['(direct)'], [1, 1])],
                ['eventName'] => [$this->ga4Row(['sign_up'], [2, 2]), $this->ga4Row(['page_view'], [0, 3])],
                ['countryId'] => [$this->ga4Row(['DE'], [4, 3]), $this->ga4Row(['(not set)'], [1, 1])],
                default => [],
            };
            if ($dimensions !== [] && $dimensions[0] !== 'date') {
                $this->assertSame('100', $request['limit']);
                $this->assertFalse($request['keepEmptyRows']);
            }
            $metadata = ['timeZone' => 'UTC'] + ($dimensions === ['countryId'] ? ['subjectToThresholding' => true] : []);

            return Http::response(['metadata' => $metadata, 'rowCount' => count($rows), 'rows' => $rows,
                'dimensionHeaders' => array_map(fn (string $name): array => ['name' => $name], $dimensions), 'metricHeaders' => array_map(fn (string $name): array => ['name' => $name], $metrics)]);
        });
        $result = (new Ga4Connector($this->http()))->collect(new SourceConfiguration('ga4', ['property_id' => '123', 'days' => 2, 'period_days' => [7]]), $this->googleCredentials(), $this->now());

        $this->assertSame(['bounces' => 1.0, 'conversions' => 2.0, 'pageviews' => 8.0, 'visit_duration' => 80.0, 'visitors' => 3.0, 'visits' => 4.0], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame(0.0, $this->valuesOn($result->daily, '2026-10-25')['conversions']);
        $this->assertSame([['date' => '2026-10-24', 'visits' => ['organic_search' => 3.0, 'shopping' => 1.0], 'is_partial' => true], ['date' => '2026-10-25', 'visits' => ['direct' => 1.0], 'is_partial' => true]], $result->channelDays);
        $this->assertSame(['referrer', 'landing_page', 'campaign', 'country', 'device', 'goal'], $result->breakdownDimensions);
        $this->assertSame([], $result->breakdownErrors);
        $this->assertSame([['google', 3.0, 2.0]], $this->items($result, 'referrer'));
        // Thresholded top lists are accepted; "(not set)" countries are unknown, and events without key events are no goals.
        $this->assertSame([['DE', 4.0, 3.0], ['unknown', 1.0, 1.0]], $this->items($result, 'country'));
        $this->assertSame([['sign_up', null, 2.0]], $this->items($result, 'goal'));
        $this->assertSame(1 + 2 + 1 + 6 * 6, count($reports));
    }

    public function test_plausible_reads_channels_goal_completions_and_top_lists(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/api/v1/sites/')) {
                return Http::response(['domain' => 'example.com', 'timezone' => 'Etc/UTC']);
            }
            $body = $request->data();
            $results = match ($body['dimensions'] ?? []) {
                ['time:day'] => [['dimensions' => ['2026-10-24'], 'metrics' => [3, 4, 8, 50, 30]], ['dimensions' => ['2026-10-25'], 'metrics' => [1, 1, 1, 100, 0]]],
                ['time:day', 'visit:channel'] => [['dimensions' => ['2026-10-24', 'Organic Search'], 'metrics' => [3]], ['dimensions' => ['2026-10-24', 'Direct'], 'metrics' => [1]], ['dimensions' => ['2026-10-25', 'Direct'], 'metrics' => [1]]],
                ['time:day', 'event:goal'] => [['dimensions' => ['2026-10-24', 'Signup'], 'metrics' => [2]], ['dimensions' => ['2026-10-24', 'Download'], 'metrics' => [1]]],
                ['visit:source'] => [['dimensions' => ['Direct / None'], 'metrics' => [2, 2]], ['dimensions' => ['Google'], 'metrics' => [3, 3]]],
                ['visit:device'] => [['dimensions' => ['Laptop'], 'metrics' => [1, 1]], ['dimensions' => ['Mobile'], 'metrics' => [4, 3]]],
                ['event:goal'] => [['dimensions' => ['Signup'], 'metrics' => [2, 2]]],
                default => [],
            };
            if (in_array('event:goal', $body['dimensions'] ?? [], true)) {
                $this->assertNotContains('visits', $body['metrics'], 'Session metrics cannot be mixed with goals.');
            }
            if (($body['dimensions'] ?? []) === ['visit:entry_page']) {
                return Http::response(['error' => 'Invalid dimension'], 400);
            }

            return Http::response(['query' => ['site_id' => 'example.com', 'metrics' => $body['metrics']], 'meta' => ['metric_warnings' => []], 'results' => $results]);
        });
        $result = (new PlausibleConnector($this->http()))->collect(new SourceConfiguration('plausible', ['site_id' => 'example.com', 'days' => 2, 'period_days' => []]), ['base_url' => 'https://plausible.example.com', 'api_key' => 'stats-key'], $this->now());

        $this->assertSame(['bounces' => 2.0, 'conversions' => 3.0, 'pageviews' => 8.0, 'visit_duration' => 120.0, 'visitors' => 3.0, 'visits' => 4.0], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame(0.0, $this->valuesOn($result->daily, '2026-10-25')['conversions']);
        $this->assertSame(['organic_search' => 3.0, 'direct' => 1.0], $result->channelDays[0]['visits']);
        $this->assertArrayHasKey('landing_page', $result->breakdownErrors);
        $this->assertNotContains('landing_page', $result->breakdownDimensions);
        $this->assertSame([['Google', 3.0, 3.0]], $this->items($result, 'referrer'));
        $this->assertSame([['mobile', 4.0, 3.0], ['desktop', 1.0, 1.0]], $this->items($result, 'device'));
        $this->assertSame([['Signup', null, 2.0]], $this->items($result, 'goal'));
    }

    public function test_umami_reads_engagement_from_daily_stats_and_breakdowns_as_visitors(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/site-one')) {
                return Http::response(['id' => 'site-one']);
            }
            if (str_ends_with($path, '/pageviews')) {
                return Http::response(['pageviews' => [['x' => '2026-10-24 00:00:00', 'y' => 6]], 'sessions' => [['x' => '2026-10-24 00:00:00', 'y' => 3]]]);
            }
            if (str_ends_with($path, '/metrics')) {
                $this->assertSame('100', (string) $request['limit']);

                return match ($request['type']) {
                    'channel' => Http::response([['x' => 'organicSearch', 'y' => 3], ['x' => 'llm', 'y' => 1]]),
                    'referrer' => Http::response([['x' => '', 'y' => 4], ['x' => 'news.example', 'y' => 2]]),
                    'event' => Http::response([['x' => 'signup', 'y' => 5]]),
                    'utmCampaign' => Http::response(['error' => 'Bad request'], 400),
                    default => Http::response([]),
                };
            }

            return Http::response(['pageviews' => 6, 'visitors' => 3, 'visits' => 4, 'bounces' => 1, 'totaltime' => 300]);
        });
        $result = (new UmamiConnector($this->http()))->collect(new SourceConfiguration('umami', ['base_url' => 'https://umami.example.com', 'website_id' => 'site-one', 'days' => 2, 'period_days' => []]), ['token' => 'synthetic-bearer'], $this->now());

        $this->assertSame(['bounces' => 1.0, 'pageviews' => 6.0, 'visit_duration' => 300.0, 'visitors' => 3.0, 'visits' => 4.0], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertNull($result->channelDays, 'Umami reports channels per period only.');
        $this->assertSame([['organic_search', 3.0, null], ['ai_assistant', 1.0, null]], $this->items($result, 'channel', 'visitors'));
        $this->assertSame([['news.example', 2.0, null]], $this->items($result, 'referrer', 'visitors'));
        $this->assertSame([['signup', null, 5.0]], $this->items($result, 'goal'));
        $this->assertSame(['campaign'], array_keys($result->breakdownErrors));
    }

    public function test_matomo_reads_channels_by_referrer_type_goal_conversions_and_top_lists(): void
    {
        Http::fake(function (Request $request) {
            $range = ($request['period'] ?? null) === 'range';

            return Http::response(match ($request['method']) {
                'SitesManager.getSiteFromId' => ['idsite' => '4', 'timezone' => 'UTC'],
                'VisitsSummary.get' => ['2026-10-24' => ['nb_uniq_visitors' => 3, 'nb_visits' => 4, 'bounce_count' => 2, 'sum_visit_length' => 400], '2026-10-25' => []],
                'Actions.get' => ['2026-10-24' => ['nb_pageviews' => 9], '2026-10-25' => []],
                'Referrers.getReferrerType' => ['2026-10-24' => [['label' => 'Suchmaschinen', 'nb_visits' => 3, 'referrer_type' => 2], ['label' => 'KI', 'nb_visits' => 1, 'segment' => 'referrerType==ai']], '2026-10-25' => []],
                'Goals.getGoals' => ['1' => ['idgoal' => '1', 'name' => 'Newsletter'], '2' => ['idgoal' => '2', 'name' => 'Contact']],
                'Goals.get' => isset($request['idGoal'])
                    ? ['2026-10-24' => ['nb_conversions' => $request['idGoal'] === '1' ? 2 : 0], '2026-10-25' => []]
                    : ['2026-10-24' => ['nb_conversions' => 2], '2026-10-25' => []],
                'Referrers.getAll' => [['label' => 'Direct Entry', 'nb_visits' => 5, 'referer_type' => 1], ['label' => 'news.example', 'nb_visits' => 2, 'referer_type' => 3]],
                'Actions.getEntryPageUrls' => [['label' => '/blog/post', 'entry_nb_visits' => 3, 'url' => 'https://example.com/blog/post?ref=x'], ['label' => 'about', 'entry_nb_visits' => 1]],
                'UserCountry.getCountry' => [['label' => 'Deutschland', 'code' => 'de', 'nb_visits' => 4], ['label' => 'Unbekannt', 'code' => 'xx', 'nb_visits' => 1]],
                'DevicesDetection.getType' => [['label' => 'Smartphone', 'segment' => 'deviceType==smartphone', 'nb_visits' => 3]],
                'Referrers.getCampaigns' => ['result' => 'error', 'message' => 'synthetic'],
                default => $range ? [] : ['2026-10-24' => [], '2026-10-25' => []],
            });
        });
        $result = (new MatomoConnector($this->http()))->collect(new SourceConfiguration('matomo', ['site_id' => '4', 'days' => 2, 'period_days' => []]), ['base_url' => 'https://matomo.example.com', 'token_auth' => 'auth-token'], $this->now());

        $this->assertSame(['bounces' => 2.0, 'conversions' => 2.0, 'pageviews' => 9.0, 'visit_duration' => 400.0, 'visitors' => 3.0, 'visits' => 4.0], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame(['organic_search' => 3.0, 'ai_assistant' => 1.0], $result->channelDays[0]['visits']);
        $this->assertSame([], $result->channelDays[1]['visits']);
        $this->assertSame([['news.example', 2.0, null]], $this->items($result, 'referrer'));
        $this->assertSame([['/blog/post', 3.0, null], ['/about', 1.0, null]], $this->items($result, 'landing_page'));
        $this->assertSame([['DE', 4.0, null], ['unknown', 1.0, null]], $this->items($result, 'country'));
        $this->assertSame([['mobile', 3.0, null]], $this->items($result, 'device'));
        $this->assertSame([['Newsletter', null, 2.0]], $this->items($result, 'goal'));
        $this->assertSame(['campaign'], array_keys($result->breakdownErrors));
    }

    public function test_posthog_reads_sessions_for_channels_and_engagement_and_one_query_per_list(): void
    {
        $queries = [];
        Http::fake(function (Request $request) use (&$queries) {
            if (str_ends_with($request->url(), '/api/projects/12345/')) {
                return Http::response(['id' => 12345, 'timezone' => 'UTC', 'created_at' => '2026-01-01T00:00:00Z']);
            }
            $query = $request->data()['query'];
            $queries[] = $query['query'];
            if (str_contains($query['query'], 'FROM sessions') && str_contains($query['query'], 'GROUP BY day')) {
                return Http::response(['columns' => ['day', 'channel', 'visits', 'bounces', 'duration'], 'results' => [['2026-10-24', 'Organic Search', 3, 1, 90], ['2026-10-24', 'AI', 1, 1, 0]]]);
            }
            if (str_contains($query['query'], 'GROUP BY day')) {
                return Http::response(['columns' => ['day', 'visitors', 'visits', 'pageviews', 'active_users'], 'results' => [['2026-10-24', 3, 4, 9, 5]]]);
            }
            $this->assertStringNotContainsString('2026-', $query['query']);
            $columns = ['item'];
            $row = [str_contains($query['query'], 'referring_domain') ? '$direct' : 'value'];
            $events = str_contains($query['query'], 'FROM events');
            for ($index = 0; $index < 6; $index++) {
                $columns[] = 'visits_'.$index;
                $row[] = 2;
                if ($events) {
                    $columns[] = 'visitors_'.$index;
                    $row[] = 1;
                }
            }
            $rows = [$row];
            if (str_contains($query['query'], 'geoip_country_code')) {
                $rows[] = ['CA', ...array_slice($row, 1)];
            }

            return Http::response(['columns' => $columns, 'results' => $rows]);
        });
        $result = (new PostHogConnector($this->http()))->collect(new SourceConfiguration('posthog', ['project_id' => '12345', 'days' => 2, 'period_days' => []], 'UTC'), ['base_url' => 'https://posthog.example.com', 'api_key' => 'phx_synthetic'], $this->now());

        $this->assertSame(['active_users' => 5.0, 'bounces' => 2.0, 'pageviews' => 9.0, 'visit_duration' => 90.0, 'visitors' => 3.0, 'visits' => 4.0], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame(['organic_search' => 3.0, 'ai_assistant' => 1.0], $result->channelDays[0]['visits']);
        $this->assertSame([], $this->items($result, 'referrer'), 'Visits without a referrer are Direct, not a referrer.');
        $this->assertSame([['CA', 2.0, 1.0], ['unknown', 2.0, 1.0]], $this->items($result, 'country'));
        $this->assertSame(['referrer', 'landing_page', 'campaign', 'country', 'device'], $result->breakdownDimensions);
        // Daily usage, sessions and one query for each of the five lists.
        $this->assertCount(7, $queries);
    }

    /**
     * The current 7-day items of a dimension as [item, visits or visitors, conversions].
     *
     * @return list<array{0: string, 1: float|null, 2: float|null}>
     */
    private function items(CollectionResult $result, string $dimension, string $count = 'visits'): array
    {
        $rows = array_filter($result->breakdowns, fn (array $row): bool => $row['dimension'] === $dimension && $row['days'] === 7 && ! $row['previous']);

        return array_values(array_map(fn (array $row): array => $dimension === 'goal' ? [$row['item'], null, $row['conversions']] : [$row['item'], $row[$count], $count === 'visits' ? $row['visitors'] : null], $rows));
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<int|float>  $metrics
     * @return array<string, mixed>
     */
    private function ga4Row(array $dimensions, array $metrics): array
    {
        return ['dimensionValues' => array_map(fn (string $value): array => ['value' => $value], $dimensions), 'metricValues' => array_map(fn (int|float $value): array => ['value' => (string) $value], $metrics)];
    }
}
