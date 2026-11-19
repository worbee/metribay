<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Metrics\Period;
use Metribay\Core\Models\BreakdownRow;
use Metribay\Core\Models\ChannelDay;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\HiddenChart;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\AudienceBreakdowns;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\DataDeletion;
use Metribay\Core\Services\MetricStore;
use Metribay\Core\Services\ProjectTransfer;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class AudienceBreakdownsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Audience', 'slug' => 'audience']);
        $this->project = $this->scoped(fn () => Project::create(['name' => 'Audience', 'slug' => 'audience']));
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback);
    }

    private function source(string $kind = 'ga4', string $label = 'Analytics', array $config = ['property_id' => '1']): Source
    {
        return $this->scoped(fn () => Source::create(['project_id' => $this->project->id, 'kind' => $kind, 'label' => $label, 'config' => $config, 'identity_hash' => hash('sha256', $kind.$label),
            'reporting_timezone' => 'UTC', 'last_success_at' => CarbonImmutable::now('UTC')]));
    }

    private function persist(Source $source, CollectionResult $result): void
    {
        $this->scoped(fn () => app(MetricStore::class)->persist($source, $result, CarbonImmutable::now('UTC')));
    }

    /** @return array<string, mixed> */
    private function row(string $dimension, string $item, ?float $visits, ?float $visitors = null, bool $previous = false, int $days = 30, ?float $conversions = null): array
    {
        $end = CarbonImmutable::now('UTC')->startOfDay()->subDays($previous ? $days : 0);

        return ['dimension' => $dimension, 'days' => $days, 'previous' => $previous, 'start_date' => $end->subDays($days - 1)->toDateString(), 'end_date' => $end->toDateString(),
            'item' => $item, 'visits' => $visits, 'visitors' => $visitors, 'conversions' => $conversions, 'is_partial' => ! $previous];
    }

    /** @param array<string, float> $visits */
    private function day(int $ago, array $visits): array
    {
        return ['date' => CarbonImmutable::now('UTC')->startOfDay()->subDays($ago)->toDateString(), 'visits' => $visits, 'is_partial' => $ago === 0];
    }

    private function daily(string $metric, int $ago, float $value): array
    {
        return ['metric' => $metric, 'date' => CarbonImmutable::now('UTC')->startOfDay()->subDays($ago)->toDateString(), 'value' => $value, 'is_partial' => $ago === 0];
    }

    public function test_channel_days_replace_their_dates_and_lists_replace_only_dimensions_that_were_read(): void
    {
        $source = $this->source();
        $this->persist($source, new CollectionResult(channelDays: [$this->day(1, ['direct' => 4.0, 'referral' => 1.0]), $this->day(2, ['direct' => 2.0])],
            breakdowns: [$this->row('referrer', 'news.example', 3.0, 2.0), $this->row('country', 'DE', 5.0, 4.0)], breakdownDimensions: ['referrer', 'country']));
        // A later collection reads one date again and only the referrers: day 2 and the countries stay as they were.
        $this->persist($source, new CollectionResult(channelDays: [$this->day(1, ['organic_search' => 6.0])],
            breakdowns: [$this->row('referrer', 'google', 9.0, 8.0)], breakdownDimensions: ['referrer']));
        $this->assertSame(['direct' => 2.0, 'organic_search' => 6.0], $this->scoped(fn () => ChannelDay::query()->orderBy('channel')->pluck('visits', 'channel')->all()));
        $this->assertSame(['DE', 'google'], $this->scoped(fn () => BreakdownRow::query()->orderBy('item')->pluck('item')->all()));
        // A dimension read with nothing in it is emptied; a collection without breakdowns leaves everything alone.
        $this->persist($source, new CollectionResult(breakdownDimensions: ['country']));
        $this->persist($source, new CollectionResult);
        $this->assertSame(['google'], $this->scoped(fn () => BreakdownRow::query()->pluck('item')->all()));
        $this->assertSame(2, $this->scoped(fn () => ChannelDay::query()->count()));
    }

    public function test_invalid_breakdowns_are_rejected_before_anything_is_written(): void
    {
        $analytics = $this->source();
        $search = $this->source('gsc', 'Search', ['site_url' => 'sc-domain:example.com']);
        $event = $this->source('posthog', 'Sign-ups', ['project_id' => '1', 'event' => 'user_signed_up']);
        $cases = [
            [$search, new CollectionResult(channelDays: [$this->day(1, ['direct' => 1.0])])],
            [$event, new CollectionResult(breakdowns: [$this->row('referrer', 'x', 1.0)], breakdownDimensions: ['referrer'])],
            [$analytics, new CollectionResult(channelDays: [$this->day(1, ['telepathy' => 1.0])])],
            [$analytics, new CollectionResult(channelDays: [$this->day(1, ['direct' => -1.0])])],
            [$analytics, new CollectionResult(channelDays: [$this->day(1, ['direct' => 1.0]), $this->day(1, ['direct' => 2.0])])],
            [$analytics, new CollectionResult(breakdowns: [$this->row('device', 'smart fridge', 1.0)], breakdownDimensions: ['device'])],
            [$analytics, new CollectionResult(breakdowns: [$this->row('country', 'Germany', 1.0)], breakdownDimensions: ['country'])],
            [$analytics, new CollectionResult(breakdowns: [$this->row('referrer', 'x', 1.0), $this->row('referrer', 'x', 2.0)], breakdownDimensions: ['referrer'])],
            [$analytics, new CollectionResult(breakdowns: [$this->row('referrer', 'x', null)], breakdownDimensions: ['referrer'])],
            [$analytics, new CollectionResult(breakdowns: [$this->row('referrer', 'x', 1.0)], breakdownDimensions: ['country'])],
            [$analytics, new CollectionResult(breakdowns: [['days' => 14] + $this->row('referrer', 'x', 1.0)], breakdownDimensions: ['referrer'])],
            [$analytics, new CollectionResult(breakdownDimensions: ['browser'])],
        ];
        foreach ($cases as $index => [$source, $result]) {
            try {
                $this->persist($source, new CollectionResult(daily: [$this->daily('visits', 1, 3.0)], channelDays: $result->channelDays, breakdowns: $result->breakdowns, breakdownDimensions: $result->breakdownDimensions));
                $this->fail('Case '.$index.' should have been rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertSame(0, $this->scoped(fn () => ChannelDay::query()->count() + BreakdownRow::query()->count() + DailyMetric::query()->count()), 'Case '.$index);
            }
        }
    }

    public function test_engagement_rates_come_from_summed_parts_not_from_averaged_daily_rates(): void
    {
        $source = $this->source();
        $this->persist($source, new CollectionResult(daily: [
            $this->daily('visits', 2, 10), $this->daily('bounces', 2, 5), $this->daily('visit_duration', 2, 1000),
            $this->daily('visits', 1, 90), $this->daily('bounces', 1, 9), $this->daily('visit_duration', 1, 900),
            // A day without its parts has no rate; it is a gap, not zero.
            $this->daily('visits', 3, 50),
        ]));
        $now = CarbonImmutable::now('UTC')->startOfDay();
        $period = Period::custom($now->subDays(3)->toDateString(), $now->subDay()->toDateString());
        $metrics = collect($this->scoped(fn () => app(DashboardData::class)->projects($period, $this->project->id))[0]['sources'][0]['metrics'])->keyBy('key');

        // 14 of 100 visits bounced (the average of 50% and 10% would be 30%); 1,900 seconds over 100 visits are 19 seconds.
        $this->assertSame(14.0, $metrics['bounce_rate']['value']);
        $this->assertSame([null, 50.0, 10.0], array_column($metrics['bounce_rate']['series'], 'value'));
        $this->assertSame(19.0, $metrics['avg_visit_duration']['value']);
        $this->assertSame(['known' => 2, 'total' => 3], $metrics['avg_visit_duration']['coverage']);
        $this->assertSame('seconds', $metrics['avg_visit_duration']['unit']);
        $this->assertNull($metrics['conversions']['value']);
    }

    public function test_channels_follow_the_reporting_period_with_shares_changes_and_a_daily_chart(): void
    {
        $source = $this->source();
        $days = [];
        for ($ago = 0; $ago < 14; $ago++) {
            $days[] = $this->day($ago, $ago < 7 ? ['organic_search' => 6.0, 'direct' => 2.0, 'email' => 1.0, 'referral' => 1.0, 'paid_search' => 1.0, 'display' => 1.0, 'affiliates' => 1.0]
                : ['organic_search' => 3.0, 'direct' => 2.0]);
        }
        $this->persist($source, new CollectionResult(daily: [$this->daily('visits', 20, 0)], channelDays: $days));
        $sources = [['id' => $source->id, 'kind' => 'ga4', 'reporting_timezone' => 'UTC', 'metric_keys' => ['visits']]];
        $report = $this->scoped(fn () => app(AudienceBreakdowns::class)->forSources($sources, 7))[$source->id]['channel'];

        $this->assertTrue($report['daily']);
        $this->assertSame(['organic_search', 'direct'], array_slice(array_column($report['rows'], 'item'), 0, 2));
        $this->assertSame(42.0, $report['rows'][0]['value']);
        $this->assertEqualsWithDelta(6 / 13, $report['rows'][0]['share'], 1e-9);
        $this->assertEqualsWithDelta(100.0, $report['rows'][0]['change'], 1e-9);
        $this->assertNull($report['rows'][2]['change'], 'A channel without visits before has no percentage change.');
        $this->assertSame(['known' => 7, 'total' => 7], $report['coverage']);
        // Five channels are drawn, the other two are combined; every day of the period is a bar.
        $this->assertSame(['organic_search', 'direct', 'affiliates', 'display', 'email', 'rest'], array_column($report['chart']['datasets'], 'key'));
        $this->assertCount(7, $report['chart']['series']);
        $this->assertSame(2.0, $report['chart']['series'][0]['values']['rest']);
        $this->assertTrue($report['partial']);
    }

    public function test_lists_use_the_closest_stored_window_and_compare_with_the_window_before(): void
    {
        $source = $this->source('umami', 'Umami', ['website_id' => '5b9e3c1a-7d42-4f0e-9a61-2c8d1e4f7a30']);
        $this->persist($source, new CollectionResult(breakdowns: [
            $this->row('referrer', 'news.example', null, 30), $this->row('referrer', 'news.example', null, 20, true),
            $this->row('referrer', 'blog.example', null, 10), $this->row('referrer', 'old.example', null, 5, true),
            $this->row('country', 'DE', null, 7), $this->row('country', 'unknown', null, 1),
        ], breakdownDimensions: ['referrer', 'country']));
        $sources = [['id' => $source->id, 'kind' => 'umami', 'reporting_timezone' => 'UTC', 'metric_keys' => ['visits']]];
        $now = CarbonImmutable::now('UTC')->startOfDay();
        $reports = $this->scoped(fn () => app(AudienceBreakdowns::class)->forSources($sources, Period::custom($now->subDays(24)->toDateString(), $now->toDateString())))[$source->id];

        $referrers = $reports['referrer'];
        $this->assertSame([30, true, 'visitors'], [$referrers['days'], $referrers['fallback'], $referrers['measure']]);
        $this->assertSame(['news.example', 'blog.example'], array_column($referrers['rows'], 'item'));
        $this->assertEqualsWithDelta(50.0, $referrers['rows'][0]['change'], 1e-9);
        $this->assertTrue($referrers['rows'][1]['new']);
        // Visitors overlap between rows: no share of a total, only a bar against the largest row.
        $this->assertNull($referrers['rows'][0]['share']);
        $this->assertSame([1.0, 1 / 3], array_column($referrers['rows'], 'bar'));
        $this->assertSame(['Germany', 'Unknown'], array_column($reports['country']['rows'], 'label'));
        $this->assertArrayNotHasKey('channel', $reports);
    }

    public function test_the_project_page_shows_blocks_conversions_and_engagement_and_hides_a_block_like_a_chart(): void
    {
        $source = $this->source();
        $daily = [];
        for ($ago = 0; $ago < 5; $ago++) {
            array_push($daily, $this->daily('visits', $ago, 20), $this->daily('visitors', $ago, 15), $this->daily('pageviews', $ago, 40), $this->daily('bounces', $ago, 8),
                $this->daily('visit_duration', $ago, 2400), $this->daily('conversions', $ago, $ago === 2 ? 3 : 0));
        }
        $this->persist($source, new CollectionResult(daily: $daily, channelDays: [$this->day(1, ['organic_search' => 15.0, 'ai_assistant' => 5.0])],
            breakdowns: [$this->row('referrer', 'news.example', 9.0, 8.0), $this->row('goal', 'sign_up', null, 3.0, false, 30, 3.0), $this->row('device', 'mobile', 6.0, 5.0)],
            breakdownDimensions: ['referrer', 'goal', 'device']));
        $url = '/w/audience/projects/'.$this->project->id;
        $this->actingAs($this->owner)->get($url)->assertOk()
            ->assertSee('Bounce rate')->assertSee('2 min 00 s')->assertSee('id="group-conversions"', false)
            ->assertSee('id="breakdown-acquisition"', false)->assertSee('Organic search')->assertSee('AI assistants')->assertSee('data-channel-chart', false)
            ->assertSee('news.example')->assertSee('id="breakdown-locations"', false)->assertSee('Mobile')
            ->assertSee('id="breakdown-goals"', false)->assertSee('sign_up');

        $this->from($url)->post($url.'/hidden-charts', ['source_id' => $source->id, 'metric_key' => 'acquisition'])->assertRedirect();
        $this->get($url)->assertOk()->assertDontSee('id="breakdown-acquisition"', false)->assertSee('id="breakdown-locations"', false);
        $this->get($url.'/settings')->assertOk()->assertSee('Acquisition');
        $this->scoped(fn () => HiddenChart::query()->firstOrFail()->delete());

        $this->owner->forceFill(['locale' => 'de'])->save();
        $this->get($url)->assertOk()->assertSee('Absprungrate')->assertSee('Organische Suche');
    }

    public function test_a_source_without_goals_or_engagement_shows_no_empty_charts_for_them(): void
    {
        $source = $this->source();
        $this->persist($source, new CollectionResult(daily: [$this->daily('visits', 1, 20), $this->daily('visitors', 1, 15), $this->daily('pageviews', 1, 40)]));
        $this->actingAs($this->owner)->get('/w/audience/projects/'.$this->project->id)->assertOk()
            ->assertSee('id="group-audience"', false)->assertDontSee('id="group-conversions"', false)
            ->assertDontSee('Bounce rate')->assertDontSee('data-query-block', false);
        $this->assertSame($source->id, $this->scoped(fn () => Source::query()->value('id')));
    }

    public function test_breakdowns_move_with_their_project_and_are_deleted_with_it(): void
    {
        $source = $this->source();
        $this->persist($source, new CollectionResult(channelDays: [$this->day(1, ['direct' => 4.0])], breakdowns: [$this->row('referrer', 'news.example', 3.0)], breakdownDimensions: ['referrer']));
        $target = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Client', 'slug' => 'client']);
        $moved = app(ProjectTransfer::class)->move($this->workspace, $this->project->id, $target, 1, false);
        $this->assertSame(0, $this->scoped(fn () => ChannelDay::query()->count() + BreakdownRow::query()->count()));
        $this->assertSame([1, 1], app(WorkspaceContext::class)->run($target, fn () => [ChannelDay::query()->count(), BreakdownRow::query()->count()]));

        app(DataDeletion::class)->project($target, $moved['project']);
        $this->assertSame(0, ChannelDay::query()->withoutGlobalScopes()->count() + BreakdownRow::query()->withoutGlobalScopes()->count());
    }
}
