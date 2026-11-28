<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Metribay\Core\Models\BreakdownMonth;
use Metribay\Core\Models\ChannelDay;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\MonthlyReport;
use Metribay\Core\Models\PeriodMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\SyncRun;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\MonthlyReportRules;
use Metribay\Core\Services\MonthlySnapshot;
use Metribay\Core\Services\ReportMonths;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class MonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private Project $project;

    private int $sources = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Reports', 'slug' => 'reports']);
        $this->project = $this->scoped(fn () => Project::create(['name' => 'Harbor', 'slug' => 'harbor']));
        $this->actingAs($owner);
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback(...));
    }

    private function source(array $extra = []): Source
    {
        $this->sources++;

        return $this->scoped(fn () => Source::create($extra + ['project_id' => $this->project->id, 'kind' => 'plausible', 'label' => 'Site '.$this->sources, 'config' => [],
            'identity_hash' => str_repeat((string) $this->sources, 64), 'reporting_timezone' => 'Europe/Sofia']));
    }

    /** One stored value per day from $from to $to; $value may depend on the date. */
    private function days(Source $source, string $metric, string $from, string $to, float|callable $value): void
    {
        $this->scoped(function () use ($source, $metric, $from, $to, $value): void {
            for ($day = CarbonImmutable::parse($from); $day->lte(CarbonImmutable::parse($to)); $day = $day->addDay()) {
                DailyMetric::query()->create(['source_id' => $source->id, 'metric' => $metric, 'date' => $day->toDateString(),
                    'value' => is_callable($value) ? $value($day) : $value, 'is_partial' => false, 'collected_at' => now()]);
            }
        });
    }

    /** @return array<string, mixed> */
    private function payload(string $month = '2026-10'): array
    {
        return $this->scoped(fn () => app(MonthlySnapshot::class)->payload($this->workspace, $this->project, $month, CarbonImmutable::parse('2026-11-05T06:00:00Z')));
    }

    /** @return array<string, array<string, mixed>> */
    private function figures(array $payload, int $index = 0): array
    {
        return collect($payload['sources'][$index]['metrics'])->keyBy('key')->all();
    }

    public function test_missing_days_stay_unknown_and_a_recorded_zero_stays_zero(): void
    {
        $source = $this->source();
        $this->days($source, 'visits', '2026-10-01', '2026-10-01', 4);
        $this->days($source, 'visits', '2026-10-02', '2026-10-02', 0);
        $this->days($source, 'visitors', '2026-10-01', '2026-10-01', 9);
        $metrics = $this->figures($this->payload());

        $this->assertEquals(4, $metrics['visits']['value']);
        $this->assertSame(2, $metrics['visits']['days_known']);
        $this->assertContains('partial', $metrics['visits']['comparability']);
        // October and September differ by one day: not worth a mark.
        $this->assertNotContains('unequal_days', $metrics['visits']['comparability']);
        $this->assertNull($metrics['visits']['year_ago']);
        $this->assertNull($metrics['visitors']['value']);
        $this->assertArrayNotHasKey('bounces', $metrics);
    }

    public function test_a_month_total_comes_from_the_provider_and_a_sealed_report_does_not_change(): void
    {
        // Connected on 10 October, with history back to the summer: the month is not "added mid-month".
        $backfilled = $this->source(['created_at' => '2026-10-10 12:00:00']);
        $this->days($backfilled, 'visits', '2026-08-01', '2026-10-31', 5);
        $late = $this->source(['enabled' => false, 'latest_data_date' => '2026-10-20']);
        $this->days($late, 'visits', '2026-10-12', '2026-10-20', 5);
        $this->scoped(function () use ($backfilled): void {
            PeriodMetric::query()->create(['source_id' => $backfilled->id, 'metric' => 'visitors', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'value' => 40, 'is_partial' => false, 'collected_at' => now()]);
            DailyMetric::query()->create(['source_id' => $backfilled->id, 'metric' => 'visitors', 'date' => '2026-10-01', 'value' => 3, 'is_partial' => false, 'collected_at' => now()]);
        });
        $snapshot = app(MonthlySnapshot::class);
        $report = $this->scoped(fn () => $snapshot->store($this->workspace, $this->project, '2026-10', true));

        $this->assertEquals(40, $this->figures($report->payload)['visitors']['value']);
        $this->assertNull($report->payload['sources'][0]['added_on']);
        $this->assertSame('2026-10-12', $report->payload['sources'][1]['added_on']);
        $this->assertSame('2026-10-20', $report->payload['sources'][1]['paused_on']);
        $this->assertSame(MonthlySnapshot::VERSION, $report->payload['version']);

        $this->scoped(fn () => DailyMetric::query()->where('metric', 'visits')->update(['value' => 999]));
        $again = $this->scoped(fn () => $snapshot->store($this->workspace, $this->project, '2026-10', true));
        $this->assertEquals(155, $this->figures($again->payload)['visits']['value']);
    }

    public function test_comparisons_mark_partial_months_and_compare_unequal_months_per_day(): void
    {
        $source = $this->source();
        // Data starts on 22 February 2025, so February 2025 is only a week.
        $this->days($source, 'visits', '2025-02-22', '2026-02-28', 10);
        $this->days($source, 'bounces', '2026-01-01', '2026-02-28', 4);
        $metrics = $this->figures($this->payload('2026-02'));
        $rules = app(MonthlyReportRules::class);

        $this->assertContains('unequal_days', $metrics['visits']['comparability']);
        $this->assertContains('year_partial', $metrics['visits']['comparability']);
        $this->assertNotContains('unequal_days', $metrics['bounce_rate']['comparability']);
        // 28 days of 10 against 31 days of 10 is no change per day, not a 10% drop.
        $this->assertEqualsWithDelta(0.0, $rules->compare($metrics['visits'], 'previous')['change'], 0.0001);
        $this->assertTrue($rules->compare($metrics['visits'], 'previous')['per_day']);
        // A week of last February is too little to compare a month with.
        $this->assertSame(7, $metrics['visits']['year_known']);
        $this->assertNull($rules->compare($metrics['visits'], 'year_ago'));
    }

    public function test_the_summary_leads_with_outcomes_and_stays_short(): void
    {
        $site = $this->source();
        $this->days($site, 'visits', '2026-07-01', '2026-10-31', 200);
        $this->days($site, 'conversions', '2026-07-01', '2026-09-30', 10);
        $this->days($site, 'conversions', '2026-10-01', '2026-10-31', 5);
        $speed = $this->source(['kind' => 'pagespeed', 'label' => 'https://harbor.example/', 'reporting_timezone' => 'UTC']);
        $this->days($speed, 'lab_tbt', '2026-09-01', '2026-09-30', 200);
        $this->days($speed, 'lab_tbt', '2026-10-01', '2026-10-31', 900);
        $reading = app(MonthlyReportRules::class)->read($this->payload());
        $text = implode(' ', $reading['sentences']);

        $this->assertStringStartsWith('Conversions fell 48% to 155 (300 in September)', $reading['headline']);
        $this->assertLessThanOrEqual(MonthlyReportRules::MAX_SENTENCES, count($reading['sentences']));
        $this->assertLessThanOrEqual(MonthlyReportRules::MAX_ACTIONS, count($reading['actions']));
        $this->assertStringNotContainsString('not certain', $text);
        $this->assertStringNotContainsString('unknown', $text);
        $this->assertStringNotContainsString('caused', $text);
        $this->assertStringNotContainsString('because', $text);
        $this->assertSame('Check what slowed https://harbor.example/', collect($reading['actions'])->last()['title']);
    }

    public function test_a_month_back_to_normal_names_last_month_as_the_exception(): void
    {
        $site = $this->source();
        $this->days($site, 'visits', '2026-07-01', '2026-08-31', 100);
        $this->days($site, 'visits', '2026-09-01', '2026-09-30', fn (CarbonImmutable $day): float => $day->day >= 15 ? 500 : 100);
        $this->days($site, 'visits', '2026-10-01', '2026-10-31', 110);
        $this->scoped(fn () => ProjectEvent::create(['project_id' => $this->project->id, 'date' => '2026-09-15', 'title' => 'Launch', 'origin' => 'manual']));
        $reading = app(MonthlyReportRules::class)->read($this->payload());

        $this->assertStringContainsString('September was likely the exception (“Launch” on 15 Sep)', $reading['sentences'][1]);
        $this->assertSame([], $reading['actions']);
    }

    public function test_an_event_that_moved_the_figure_is_named_as_a_likely_driver(): void
    {
        $site = $this->source();
        $this->days($site, 'visits', '2026-07-01', '2026-10-14', 100);
        $this->days($site, 'visits', '2026-10-15', '2026-11-05', 200);
        $this->scoped(function (): void {
            ProjectEvent::create(['project_id' => $this->project->id, 'date' => '2026-10-15', 'title' => 'New pricing page', 'origin' => 'manual']);
            ProjectEvent::create(['project_id' => $this->project->id, 'date' => '2026-10-15', 'title' => 'New pricing page', 'origin' => 'manual']);
            ProjectEvent::create(['project_id' => $this->project->id, 'date' => '2026-10-16', 'title' => 'production · abc123', 'origin' => 'github_deployment']);
        });
        $payload = $this->payload();
        $reading = app(MonthlyReportRules::class)->read($payload);

        $this->assertSame(['New pricing page'], array_column($payload['events'], 'title'));
        $this->assertSame(3, $payload['event_count']);
        $this->assertStringContainsString('likely lines up with “New pricing page” on 15 Oct', $reading['sentences'][1]);
    }

    public function test_collection_and_uptime_problems_are_read_from_the_month(): void
    {
        $site = $this->source();
        $this->days($site, 'visits', '2026-09-01', '2026-10-31', 100);
        $monitor = $this->source(['kind' => 'uptimerobot', 'label' => 'https://harbor.example', 'reporting_timezone' => 'UTC']);
        $this->days($monitor, 'uptime_24h', '2026-10-01', '2026-10-31', fn (CarbonImmutable $day): float => $day->day === 7 ? 95.0 : 100.0);
        $this->scoped(function () use ($site): void {
            SyncRun::query()->create(['source_id' => $site->id, 'started_at' => '2026-10-20 06:00:00', 'finished_at' => '2026-10-20 06:01:00', 'status' => 'success']);
            SyncRun::query()->create(['source_id' => $site->id, 'started_at' => '2026-10-30 06:00:00', 'finished_at' => '2026-10-30 06:01:00', 'status' => 'failed']);
            // A later failure does not belong to October.
            SyncRun::query()->create(['source_id' => $site->id, 'started_at' => '2026-12-01 06:00:00', 'finished_at' => '2026-12-01 06:01:00', 'status' => 'failed']);
        });
        $payload = $this->payload();
        $reading = app(MonthlyReportRules::class)->read($payload);

        $this->assertSame(['runs' => 2, 'failed_runs' => 1, 'failing' => true], array_intersect_key($payload['sources'][0]['health'], ['runs' => 0, 'failed_runs' => 0, 'failing' => 0]));
        $this->assertEqualsWithDelta(72.0, $payload['sources'][1]['health']['downtime_minutes'], 0.01);
        $this->assertSame('Reconnect Site 1', $reading['actions'][0]['title']);
        $this->assertSame('Find the cause of the downtime', $reading['actions'][1]['title']);
        $this->assertStringContainsString('was down for about 72 min', implode(' ', $reading['sentences']));
    }

    public function test_a_host_ranking_reorders_candidates_but_keeps_collection_problems(): void
    {
        $site = $this->source();
        $this->days($site, 'visits', '2026-09-01', '2026-09-30', 100);
        $this->days($site, 'visits', '2026-10-01', '2026-10-31', 200);
        $this->days($site, 'conversions', '2026-09-01', '2026-09-30', 10);
        $this->days($site, 'conversions', '2026-10-01', '2026-10-31', 5);
        $this->scoped(fn () => SyncRun::query()->create(['source_id' => $site->id, 'started_at' => '2026-10-30 06:00:00', 'status' => 'failed']));
        $payload = $this->payload();
        $rules = app(MonthlyReportRules::class);
        $candidates = $rules->candidates($payload);
        $ids = array_column($candidates['sentences'], 'id');

        $this->assertContains('move:'.$site->id.':conversions', $ids);
        $this->assertArrayNotHasKey('text', $candidates['sentences'][0]);
        $reading = $rules->read($payload, ['sentences' => ['second:'.$site->id.':visits'], 'actions' => []]);
        $this->assertStringStartsWith('Collection for Site 1 was failing', $reading['sentences'][0]);
        $this->assertStringStartsWith('Visits rose 107%', $reading['sentences'][1]);
        $this->assertSame('Reconnect Site 1', $reading['actions'][0]['title']);
        // An unknown id keeps the rules' own order.
        $this->assertSame($rules->read($payload)['sentences'], $rules->read($payload, ['sentences' => ['invented'], 'actions' => []])['sentences']);
    }

    public function test_the_channel_archive_follows_late_data_until_the_report_is_sealed(): void
    {
        $source = $this->source();
        $this->days($source, 'visits', '2026-10-01', '2026-10-02', 5);
        $channel = fn (string $date, float $visits) => $this->scoped(fn () => ChannelDay::query()->create(['source_id' => $source->id, 'date' => $date, 'channel' => 'organic_search', 'visits' => $visits, 'is_partial' => false, 'collected_at' => now()]));
        $channel('2026-10-01', 5);
        $snapshot = app(MonthlySnapshot::class);
        $this->scoped(fn () => $snapshot->store($this->workspace, $this->project, '2026-10', false));
        $channel('2026-10-02', 3);
        $this->scoped(fn () => $snapshot->store($this->workspace, $this->project, '2026-10', true));
        $channel('2026-10-03', 7);
        $report = $this->scoped(fn () => $snapshot->store($this->workspace, $this->project, '2026-10', true));

        $this->assertEquals([['channel' => 'organic_search', 'visits' => 8]], $report->payload['sources'][0]['channels']);
        $this->assertSame(1, $this->scoped(fn () => BreakdownMonth::query()->where('dimension', 'channel')->count()));
    }

    public function test_the_report_page_is_read_only_and_links_back_to_the_month(): void
    {
        $source = $this->source();
        $this->days($source, 'visits', '2026-09-01', '2026-10-31', 6);
        $this->scoped(function (): void {
            app(MonthlySnapshot::class)->store($this->workspace, $this->project, '2026-09', true);
            app(MonthlySnapshot::class)->store($this->workspace, $this->project, '2026-10', true);
        });
        $base = '/w/reports/projects/'.$this->project->id.'/reports';

        $this->get($base.'/2026-10')->assertOk()->assertSee('Summary')->assertSee('Explore this month')
            ->assertSee('from=2026-10-01', false)->assertSee('to=2026-10-31', false)
            ->assertSee('rel="prev"', false)->assertDontSee('rel="next"', false)->assertDontSee('<input type="text"', false);
        $this->get($base.'?month=2026-09')->assertRedirect($base.'/2026-09');
        $this->get($base.'/2026-08')->assertNotFound();
        $this->get($base)->assertOk()->assertSee('What changed in October 2026');
    }

    public function test_a_project_delete_removes_its_report(): void
    {
        $this->source();
        $this->scoped(fn () => app(MonthlySnapshot::class)->store($this->workspace, $this->project, '2026-10', true));
        $this->assertSame(1, DB::table('monthly_reports')->count());
        $this->delete('/w/reports/projects/'.$this->project->id)->assertRedirect();
        $this->assertSame(0, MonthlyReport::query()->withoutGlobalScope('workspace')->count());
    }

    public function test_collection_asks_for_closed_months_the_report_still_needs(): void
    {
        $source = $this->source(['reporting_timezone' => 'UTC']);
        $this->days($source, 'visits', '2026-07-15', '2026-11-03', 6);
        $this->scoped(function () use ($source): void {
            PeriodMetric::query()->create(['source_id' => $source->id, 'metric' => 'visitors', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'value' => 40, 'is_partial' => false, 'collected_at' => now()]);
            PeriodMetric::query()->create(['source_id' => $source->id, 'metric' => 'visitors', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'value' => 40, 'is_partial' => true, 'collected_at' => now()]);
            BreakdownMonth::query()->create(['source_id' => $source->id, 'dimension' => 'referrer', 'month' => '2026-09-01', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'item_count' => 0, 'collected_at' => now()]);
        });
        $now = CarbonImmutable::parse('2026-11-03T10:00:00Z');

        // October is provisional, September is final, July and August are still missing; the current month is never asked for.
        [$periods, $breakdowns] = $this->scoped(fn () => app(ReportMonths::class)->toRead($source, $now, true));
        $this->assertSame(['2026-10', '2026-08', '2026-07'], $periods);
        $this->assertSame(['2026-10'], $breakdowns);
        $this->assertSame([], $this->scoped(fn () => app(ReportMonths::class)->toRead($source, $now, false))[1]);
        $github = $this->source(['kind' => 'github']);
        $this->assertSame([[], []], $this->scoped(fn () => app(ReportMonths::class)->toRead($github, $now, true)));
    }

    public function test_the_command_refreshes_until_the_fifth_then_seals_and_catches_up(): void
    {
        $source = $this->source();
        $this->days($source, 'visits', '2026-08-01', '2026-10-31', 6);
        $this->scoped(fn () => app(MonthlySnapshot::class)->store($this->workspace, $this->project, '2026-09', false));

        $this->travelTo(CarbonImmutable::parse('2026-11-03T06:10:00Z'));
        $this->artisan('metribay:monthly-reports')->assertSuccessful();
        $this->assertNull($this->scoped(fn () => MonthlyReport::query()->whereDate('month', '2026-10-01')->value('sealed_at')));

        // The run on the 5th was missed; the 6th seals October and the September report left open.
        $this->travelTo(CarbonImmutable::parse('2026-11-06T06:10:00Z'));
        $this->artisan('metribay:monthly-reports')->assertSuccessful();
        $this->assertSame(0, $this->scoped(fn () => MonthlyReport::query()->whereNull('sealed_at')->count()));
        $this->assertSame(2, $this->scoped(fn () => MonthlyReport::query()->count()));
    }
}
