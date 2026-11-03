<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Metribay\Core\Demo\SamplePortfolio;
use Metribay\Core\Demo\SampleSources;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\PeriodMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\SearchQuery;
use Metribay\Core\Models\SearchQueryMonth;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\DemoData;
use Metribay\Core\Services\ProviderCatalog;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    private const DAYS = 100;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $this->workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Samples', 'slug' => 'samples']);
    }

    public function test_the_same_seed_and_day_give_the_same_portfolio(): void
    {
        $now = CarbonImmutable::parse('2026-11-03 15:20:00', 'UTC');
        $first = serialize((new SamplePortfolio(7, $now, self::DAYS))->projects());

        $this->assertSame($first, serialize((new SamplePortfolio(7, $now, self::DAYS))->projects()));
        $this->assertNotSame($first, serialize((new SamplePortfolio(8, $now, self::DAYS))->projects()));
    }

    public function test_the_portfolio_covers_every_provider_metric_and_event_origin(): void
    {
        $projects = (new SamplePortfolio(SamplePortfolio::SEED, CarbonImmutable::parse('2026-11-03 15:20:00', 'UTC'), self::DAYS))->projects();
        $metrics = [];
        $origins = [];
        foreach ($projects as $project) {
            $this->assertStringEndsWith('.example', $project->domain);
            foreach ($project->sources as $source) {
                foreach ($source->collections as $collection) {
                    foreach ($collection['result']->daily as $row) {
                        $metrics[$source->kind][$row['metric']] = true;
                    }
                    foreach ($collection['result']->events as $event) {
                        $origins[$event['origin']] = true;
                    }
                }
            }
        }

        $this->assertCount(7, $projects);
        $this->assertCount(7, array_unique(array_map(fn ($project) => $project->audience, $projects)));
        foreach (array_keys(ProviderCatalog::all()) as $kind) {
            $this->assertEqualsCanonicalizing(ProviderCatalog::metricKeys($kind), array_keys($metrics[$kind] ?? []), $kind);
        }
        $this->assertEqualsCanonicalizing(['github_release', 'github_deployment', 'azure_devops_deployment'], array_keys($origins));
    }

    public function test_stored_samples_keep_the_rules_of_real_data(): void
    {
        $this->assertSame(7, app(DemoData::class)->populate($this->workspace, days: self::DAYS));
        $today = CarbonImmutable::now('UTC')->startOfDay();

        app(WorkspaceContext::class)->run($this->workspace, function () use ($today): void {
            $this->assertSame(7, Project::query()->where('domain', 'like', '%.example')->where('name', 'like', '% · Sample')->count());
            // Collection is on in this installation, so samples stay paused.
            $this->assertSame(0, Source::query()->where('enabled', true)->count());
            $source = fn (string $project, string $kind): Source => Source::query()->where('kind', $kind)->whereHas('project', fn ($q) => $q->where('slug', 'sample-'.$project))->firstOrFail();
            $series = fn (Source $source, string $metric) => DailyMetric::query()->where('source_id', $source->id)->where('metric', $metric)->orderBy('date')->get()
                ->mapWithKeys(fn (DailyMetric $row): array => [$row->date->toDateString() => (float) $row->value]);

            foreach (Source::query()->whereIn('kind', ['ga4', 'umami'])->get() as $analytics) {
                [$visits, $visitors, $views] = [$series($analytics, 'visits'), $series($analytics, 'visitors'), $series($analytics, 'pageviews')];
                foreach ($visits as $date => $count) {
                    $this->assertGreaterThanOrEqual($visitors[$date], $count, $analytics->label.' '.$date);
                    $this->assertGreaterThanOrEqual($count, $views[$date], $analytics->label.' '.$date);
                }
                $this->assertGreaterThanOrEqual(1.7, $views->sum() / $visits->sum());
                $this->assertLessThanOrEqual(3.0, $views->sum() / $visits->sum());
                // Unique visitors of a window are never the sum of its days.
                foreach (PeriodMetric::query()->where('source_id', $analytics->id)->where('metric', 'visitors')->get() as $period) {
                    $days = $visitors->filter(fn (float $value, string $date): bool => $date >= $period->start_date->toDateString() && $date <= $period->end_date->toDateString());
                    $this->assertLessThan($days->sum(), $period->value);
                    $this->assertGreaterThanOrEqual($days->max(), $period->value);
                }
            }

            foreach (Source::query()->whereIn('kind', ['gsc', 'bing'])->get() as $search) {
                [$clicks, $impressions] = [$series($search, 'clicks'), $series($search, 'impressions')];
                foreach ($clicks as $date => $count) {
                    $this->assertLessThanOrEqual($impressions[$date], $count);
                }
                // Top queries leave out rare ones, so they explain part of the site's clicks.
                $top = SearchQuery::query()->where('source_id', $search->id)->where('days', 90)->where('previous', false)->get();
                $site = $clicks->filter(fn (float $value, string $date): bool => $date >= $top->min('start_date')->toDateString() && $date <= $top->max('end_date')->toDateString())->sum();
                $this->assertGreaterThan(0.5 * $site, $top->sum('clicks'));
                $this->assertLessThan(0.9 * $site, $top->sum('clicks'));
                $this->assertGreaterThan(0, SearchQueryMonth::query()->where('source_id', $search->id)->count());
            }

            // Search Console's two freshest days and today's counts are provisional; GA4 keeps three days open.
            $gsc = $source('tallybird', 'gsc');
            $this->assertSame(2, DailyMetric::query()->where('source_id', $gsc->id)->where('metric', 'clicks')->where('is_partial', true)->count());
            $this->assertSame(3, DailyMetric::query()->where('source_id', $source('tallybird', 'ga4')->id)->where('metric', 'visits')->where('is_partial', true)->count());

            // The self-hosted tracker was down for two days: those days are unknown, not zero.
            $umami = $series($source('kestrel-photo', 'umami'), 'visits');
            $this->assertArrayNotHasKey($today->subDays(63)->toDateString(), $umami->all());
            $this->assertArrayNotHasKey($today->subDays(64)->toDateString(), $umami->all());
            $this->assertArrayHasKey($today->subDays(65)->toDateString(), $umami->all());
            $this->assertNotContains(0.0, $umami->all());

            // Stories line up with their events.
            $visits = $series($source('tallybird', 'ga4'), 'visits');
            $launch = ProjectEvent::query()->where('title', 'Product Hunt launch')->firstOrFail()->date->toDateString();
            // GA4 reports in Berlin time, so late in the UTC day the spike sits one day after the UTC event date.
            $this->assertGreaterThan(3 * $visits->median(), max($visits[$launch] ?? 0, $visits[CarbonImmutable::parse($launch)->addDay()->toDateString()] ?? 0));
            $downloads = $series($source('quillform', 'wporg'), 'downloads');
            $this->assertGreaterThan(2 * $downloads->median(), $downloads[$today->subDays(8)->toDateString()]);
            $this->assertTrue(ProjectEvent::query()->where('origin', 'github_release')->where('title', 'v4.3.0')->exists());
            $this->assertLessThan(100.0, $series($source('tallybird', 'uptimerobot'), 'uptime_24h')[$today->subDays(23)->toDateString()]);
            $score = $series($source('alder-and-finch', 'pagespeed'), 'lab_score');
            $this->assertGreaterThan($score[$today->subDays(25)->toDateString()] + 15, $score[$today->subDays(5)->toDateString()]);

            $overview = app(DashboardData::class)->projects(30);
            $this->assertCount(7, $overview);
            $this->assertNotNull(collect($overview)->firstWhere('slug', 'sample-switchback-journal')['summary']['traffic']['change']);
        });

        // Adding the samples again replaces them.
        app(DemoData::class)->populate($this->workspace, days: self::DAYS);
        $this->assertSame(7, app(WorkspaceContext::class)->run($this->workspace, fn () => Project::query()->count()));
    }

    public function test_samples_are_active_only_when_collection_is_switched_off(): void
    {
        config(['metribay.collection.enabled' => false]);
        Bus::fake();
        app(DemoData::class)->populate($this->workspace, days: 30);

        $this->assertSame(0, app(WorkspaceContext::class)->run($this->workspace, fn () => Source::query()->where('enabled', false)->count()));
        $this->artisan('metribay:sync')->expectsOutputToContain('switched off')->assertSuccessful();
        Bus::assertNothingDispatched();
    }

    public function test_the_command_adds_labelled_samples_and_only_rebuilds_a_dedicated_database(): void
    {
        $this->artisan('metribay:demo', ['--email' => 'owner@example.test', '--days' => 30])->expectsOutputToContain('paused')->assertSuccessful();
        $this->assertSame(7, app(WorkspaceContext::class)->run($this->workspace, fn () => Project::query()->where('slug', 'like', 'sample-%')->count()));

        $this->artisan('metribay:demo', ['--fresh' => true])->expectsOutputToContain('demo.sqlite')->assertFailed();
        $this->artisan('metribay:demo', ['--days' => 5])->assertFailed();
    }

    public function test_lighthouse_scores_follow_the_published_curves(): void
    {
        // At each metric's 10th-percentile control point the score is 0.9; at the median it is 0.5.
        $this->assertEqualsWithDelta(0.9, SampleSources::lighthouse('mobile', 1800, 3387, 2500, 200, 0.1), 0.001);
        $this->assertEqualsWithDelta(0.5, SampleSources::lighthouse('desktop', 1600, 2300, 2400, 350, 0.25), 0.001);
    }
}
