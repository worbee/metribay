<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Livewire\Dashboard;
use Metribay\Core\Metrics\Period;
use Metribay\Core\Metrics\ProjectLayout;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\MetricStore;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ReportingPeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Project $project;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-11-03T12:00:00Z');
        CarbonImmutable::setTestNow($this->now);
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Periods', 'slug' => 'periods']);
        $this->project = $this->scoped(fn () => Project::create(['name' => 'Periods', 'slug' => 'periods']));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback);
    }

    /** @param list<array{0: string, 1: string, 2: float}> $rows metric, date, value */
    private function source(string $kind, string $label, array $rows): Source
    {
        return $this->scoped(function () use ($kind, $label, $rows) {
            $source = Source::create(['project_id' => $this->project->id, 'kind' => $kind, 'label' => $label, 'config' => [], 'identity_hash' => hash('sha256', $label),
                'reporting_timezone' => 'UTC', 'last_success_at' => $this->now, 'latest_data_date' => $this->now->toDateString()]);
            app(MetricStore::class)->persist($source, new CollectionResult(array_map(fn (array $row): array => ['metric' => $row[0], 'date' => $row[1], 'value' => $row[2], 'is_partial' => false], $rows)), $this->now);

            return $source;
        });
    }

    /** @return array<string, mixed> */
    private function metric(Period $period, string $key): array
    {
        $sources = $this->scoped(fn () => app(DashboardData::class)->projects($period, $this->project->id))[0]['sources'];

        return collect($sources)->flatMap(fn (array $source) => $source['metrics'])->firstWhere('key', $key);
    }

    public function test_periods_are_read_from_input_and_limited(): void
    {
        $this->assertSame(30, Period::fromInput('30')->days);
        $this->assertTrue(Period::fromInput(1)->isYesterday());
        $this->assertNull(Period::fromInput(14));
        $custom = Period::fromInput(30, '2026-10-01', '2026-10-31');
        $this->assertSame([31, '2026-10'], [$custom->days, $custom->calendarMonth()]);
        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-31'], $custom->query());
        $this->assertSame('1 Oct – 31 Oct 2026', $custom->label());
        $this->assertNull(Period::fromInput(30, '2026-10-01', '2026-10-15')->calendarMonth());
        foreach ([['2026-10-31', '2026-10-01'], ['2026-02-30', '2026-03-01'], ['2026-10-01', ''], ['1999-12-31', '2000-01-01'], ['2026-11-01', '2026-11-06'], ['2024-01-01', '2026-01-02'], [['2026-10-01'], '2026-10-02']] as [$from, $to]) {
            $this->assertNull(Period::fromInput(30, $from, $to), json_encode([$from, $to]));
        }
        $this->assertSame(Period::MAX_DAYS, Period::custom('2024-11-03', '2026-11-03')->days);
    }

    public function test_windows_follow_the_period_and_compare_with_the_days_before(): void
    {
        $yesterday = Period::preset(1);
        $this->assertSame(['2026-11-02', '2026-11-02'], array_map(fn ($date) => $date->toDateString(), $yesterday->window('UTC', $this->now)));
        $this->assertSame(['2026-11-01', '2026-11-01'], array_map(fn ($date) => $date->toDateString(), $yesterday->previousWindow('UTC', $this->now)));
        // A custom range is calendar dates in every timezone.
        $custom = Period::custom('2026-10-11', '2026-10-20');
        $this->assertSame(['2026-10-01', '2026-10-10'], array_map(fn ($date) => $date->toDateString(), $custom->previousWindow('America/Los_Angeles', $this->now)));
        $this->assertSame(['2026-10-11', '2026-10-20'], array_map(fn ($date) => $date->toDateString(), $custom->window('Pacific/Auckland', $this->now)));
    }

    public function test_yesterday_uses_daily_values_and_its_unique_visitors(): void
    {
        $this->source('umami', 'Website', [['visits', '2026-11-03', 99.0], ['visits', '2026-11-02', 12.0], ['visits', '2026-11-01', 8.0], ['visitors', '2026-11-02', 7.0]]);
        $visits = $this->metric(Period::preset(1), 'visits');
        $this->assertSame([12.0, 8.0, 50.0], [$visits['value'], $visits['previous'], $visits['change']]);
        $this->assertSame(['2026-11-02'], array_column($visits['series'], 'date'));
        // Visitors are not additive; for one day the daily value is the day's unique count.
        $this->assertSame(7.0, $this->metric(Period::preset(1), 'visitors')['value']);
        $this->assertNull($this->metric(Period::preset(7), 'visitors')['value']);
    }

    public function test_custom_ranges_sum_their_own_dates(): void
    {
        $this->source('umami', 'Website', [['visits', '2026-10-20', 5.0], ['visits', '2026-10-11', 3.0], ['visits', '2026-10-10', 4.0], ['visits', '2026-10-21', 100.0]]);
        $visits = $this->metric(Period::custom('2026-10-11', '2026-10-20'), 'visits');
        $this->assertSame([8.0, 4.0, 10], [$visits['value'], $visits['previous'], count($visits['series'])]);
        $this->assertSame(['2026-10-11', '2026-10-20'], [$visits['start_date'], $visits['end_date']]);
    }

    public function test_snapshots_compare_their_latest_value_as_a_difference(): void
    {
        $this->source('pagespeed', 'Home', [['lab_score', '2026-11-02', 82.0], ['lab_score', '2026-10-01', 70.0], ['lab_lcp', '2026-11-02', 2100.0], ['lab_lcp', '2026-10-01', 2600.0]]);
        $score = $this->metric(Period::preset(30), 'lab_score');
        $this->assertSame([82.0, 70.0, 12.0], [$score['value'], $score['previous'], $score['delta']]);
        $this->assertNull($score['change']);
        $this->assertSame(-500.0, $this->metric(Period::preset(30), 'lab_lcp')['delta']);

        $html = preg_replace('/<!--\[if [A-Z]+\]><!\[endif\]-->/', '', $this->actingAs($this->owner)->get('/w/periods/projects/'.$this->project->id)->assertOk()->getContent());
        // Higher scores and lower timings are both improvements.
        $this->assertMatchesRegularExpression('/cell-change is-good" title="Change from the last value of the previous period">\+12</', $html);
        $this->assertMatchesRegularExpression('/cell-change is-good" title="Change from the last value of the previous period">−500 ms</', $html);
    }

    public function test_project_page_offers_yesterday_and_custom_ranges(): void
    {
        $this->source('umami', 'Website', [['visits', '2026-11-02', 12.0], ['visits', '2026-11-01', 8.0], ['visits', '2026-10-20', 5.0]]);
        $url = '/w/periods/projects/'.$this->project->id;
        $html = $this->actingAs($this->owner)->get($url.'?days=1')->assertOk()
            ->assertSee('title="Change from the preceding 1-day period"', false)->assertSee('href="'.url('/w/periods?days=1').'"', false)->getContent();
        $this->assertMatchesRegularExpression('/days=1"\s+aria-current="page"\s*>Yesterday</', $html);
        // One day: number tiles without a line chart.
        $this->assertStringContainsString('metric metric-n', $html);
        $this->assertStringNotContainsString('data-chart-config', $html);

        $custom = $this->get($url.'?from=2026-10-11&to=2026-10-20')->assertOk()->assertSee('name="from" value="2026-10-11"', false)
            ->assertSee('href="'.e(url('/w/periods?from=2026-10-11&to=2026-10-20')).'"', false)->getContent();
        $this->assertMatchesRegularExpression('/<summary\s+aria-current="page"\s*>11 Oct – 20 Oct 2026<\/summary>/u', $custom);
        $this->assertMatchesRegularExpression('/<\/span>11 Oct – 20 Oct 2026<\/button>/u', $custom);
        $this->get($url.'?from=2026-10-20&to=2026-10-11')->assertStatus(422);
        $this->get($url.'?days=14')->assertStatus(422);
    }

    public function test_overview_offers_yesterday_and_validates_custom_ranges(): void
    {
        $this->source('umami', 'Website', [['visits', '2026-11-02', 12.0], ['visits', '2026-10-15', 5.0]]);
        $this->actingAs($this->owner);
        $component = Livewire::test(Dashboard::class, ['workspaceId' => $this->workspace->id])
            ->call('choosePeriod', '1')->assertSet('days', 1)->assertSee('Projects with their headline metrics: Yesterday')
            ->call('choosePeriod', 'custom')->assertSet('editingRange', true)->assertSet('draftFrom', '2026-11-02')->assertSet('draftTo', '2026-11-02')
            ->set('draftFrom', '2026-10-20')->set('draftTo', '2026-10-01')->call('applyRange')->assertHasErrors('range')->assertSet('from', '')
            ->set('draftFrom', '2026-10-01')->set('draftTo', '2026-10-31')->call('applyRange')->assertHasNoErrors()
            ->assertSet('from', '2026-10-01')->assertSet('to', '2026-10-31')->assertSee('1 Oct – 31 Oct 2026')
            ->assertSee('?from=2026-10-01&amp;to=2026-10-31', false);
        $component->call('choosePeriod', '7')->assertSet('from', '')->assertSet('days', 7);
        $this->get('/w/periods?from=2026-10-01&to=2026-10-31')->assertOk()->assertSee('Change dates');
    }

    public function test_overview_uptime_has_a_sparkline_of_the_lowest_daily_uptime(): void
    {
        $this->source('uptimerobot', 'Web', [['uptime_24h', '2026-11-02', 100.0], ['uptime_24h', '2026-11-03', 99.0], ['monitor_status', '2026-11-03', 2.0]]);
        $this->source('uptimerobot', 'Workers', [['uptime_24h', '2026-11-02', 94.0], ['uptime_24h', '2026-11-03', 100.0], ['monitor_status', '2026-11-03', 2.0]]);
        $uptime = $this->scoped(fn () => app(DashboardData::class)->projects(7, $this->project->id))[0]['summary']['uptime'];
        $this->assertSame([94.0, 99.0], array_slice($uptime['series'], -2));
        $this->actingAs($this->owner)->get('/w/periods?days=7')->assertOk()->assertSee('class="sparkline"', false);
    }

    public function test_a_monitor_that_is_down_now_needs_attention_in_a_past_range_too(): void
    {
        $this->source('uptimerobot', 'Workers', [['monitor_status', '2026-11-03', 9.0], ['monitor_status', '2026-10-05', 2.0]]);
        $source = $this->scoped(fn () => app(DashboardData::class)->projects(Period::custom('2026-10-01', '2026-10-10'), $this->project->id))[0]['sources'][0];
        $this->assertSame('failed', $source['status']);
        $this->assertSame(2.0, collect($source['metrics'])->firstWhere('key', 'monitor_status')['value']);
    }

    public function test_single_day_layout_has_no_lead_chart(): void
    {
        $source = ['id' => 1, 'label' => 'Web', 'kind' => 'umami', 'status' => 'healthy', 'enabled' => true, 'last_success_at' => 'now',
            'metrics' => [['key' => 'visits', 'label' => 'Visits', 'unit' => 'count', 'value' => 1.0, 'series' => [['date' => '2026-11-02', 'value' => 1.0, 'partial' => false]], 'previous_series' => []],
                ['key' => 'pageviews', 'label' => 'Page views', 'unit' => 'count', 'value' => 2.0, 'series' => [['date' => '2026-11-02', 'value' => 2.0, 'partial' => false]], 'previous_series' => []]]];
        $this->assertSame(['l', 's'], array_column(ProjectLayout::rows([$source])[0][0]['cards'], 'size'));
        $this->assertSame(['n', 'n'], array_column(ProjectLayout::rows([$source], true)[0][0]['cards'], 'size'));
    }
}
