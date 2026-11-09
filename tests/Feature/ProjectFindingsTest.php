<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Metrics\ProjectFindings;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\SearchQuery;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ProjectFindingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 15:00:00', 'UTC'));
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Findings', 'slug' => 'findings']);
        $this->project = $this->scoped(fn () => Project::create(['name' => 'Journal', 'slug' => 'journal', 'domain' => 'journal.example.test']));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function scoped(callable $callback, ?Workspace $workspace = null): mixed
    {
        return app(WorkspaceContext::class)->run($workspace ?? $this->workspace, $callback);
    }

    /** @param array<string, mixed> $state */
    private function source(string $kind, string $label, array $state = []): Source
    {
        return $this->scoped(function () use ($kind, $label, $state) {
            $source = Source::create([
                'project_id' => $this->project->id, 'kind' => $kind, 'label' => $label, 'config' => [],
                'identity_hash' => hash('sha256', $kind.$label.spl_object_id($this)), 'reporting_timezone' => 'UTC',
                'last_success_at' => CarbonImmutable::now('UTC'), 'latest_data_date' => '2026-11-09', 'enabled' => true,
            ]);
            $source->forceFill($state)->save();

            return $source->fresh();
        });
    }

    /** @param array<string, float> $values date => value */
    private function clicks(Source $source, array $values, array $partial = []): void
    {
        $this->scoped(function () use ($source, $values, $partial) {
            foreach ($values as $date => $value) {
                DailyMetric::create([
                    'source_id' => $source->id, 'metric' => 'clicks', 'date' => $date, 'value' => $value,
                    'is_partial' => in_array($date, $partial, true), 'collected_at' => CarbonImmutable::now('UTC'),
                ]);
            }
        });
    }

    /** @return array<string, float> */
    private function window(float $previous, float $current, string $start = '2026-10-27'): array
    {
        $values = [];
        $day = CarbonImmutable::parse($start);
        for ($i = 0; $i < 14; $i++) {
            $values[$day->addDays($i)->toDateString()] = $i < 7 ? $previous : $current;
        }

        return $values;
    }

    /** @param array<string, mixed> $extra */
    private function gsc(Source $source, array $extra = []): array
    {
        return $extra + [
            'id' => $source->id, 'kind' => 'gsc', 'label' => $source->label, 'status' => 'healthy', 'enabled' => true,
            'reporting_timezone' => 'UTC', 'hidden_keys' => [],
        ];
    }

    /** @param list<array<string, mixed>> $sources */
    private function findings(array $sources, ?int $projectId = null): array
    {
        return $this->scoped(fn () => app(ProjectFindings::class)->forSources($sources, $projectId ?? $this->project->id));
    }

    public function test_a_live_monitor_incident_survives_a_historical_range(): void
    {
        $monitor = $this->source('uptimerobot', 'Workers');
        $this->scoped(function () use ($monitor) {
            foreach (['2026-10-01' => 2.0, '2026-10-15' => 2.0, '2026-11-09' => 9.0] as $date => $value) {
                DailyMetric::create([
                    'source_id' => $monitor->id, 'metric' => 'monitor_status', 'date' => $date, 'value' => $value,
                    'is_partial' => false, 'collected_at' => CarbonImmutable::now('UTC'), 'observed_at' => CarbonImmutable::now('UTC'),
                    'window_end' => CarbonImmutable::parse($date.' 15:00:00', 'UTC'),
                ]);
            }
        });

        $project = $this->scoped(fn () => app(DashboardData::class)->projects(\Metribay\Core\Metrics\Period::custom('2026-10-01', '2026-10-15'), $this->project->id)[0]);
        $this->assertTrue($project['sources'][0]['current_monitor']['incident']);
        $this->assertSame(9, $project['sources'][0]['current_monitor']['value']);
        $this->assertSame(2.0, collect($project['sources'][0]['metrics'])->firstWhere('key', 'monitor_status')['value']);

        $url = '/w/findings/projects/'.$this->project->id.'?view=overview&from=2026-10-01&to=2026-10-15';
        $this->actingAs($this->owner)->get($url)->assertOk()
            ->assertSee('Workers: Down.')
            ->assertSee('UptimeRobot · checked 9 Nov 2026, 15:00 UTC')
            ->assertSee('#chart-'.$monitor->id.'-monitor_status', false)
            ->assertSee('Open Reliability')
            ->assertDontSee('No significant changes in the data we checked.');

        $this->get('/w/findings/projects/'.$this->project->id.'?from=2026-10-01&to=2026-10-15')->assertOk()
            ->assertSee('Current: Down, checked 9 Nov 2026, 15:00 UTC');
    }

    public function test_paused_failed_and_stale_monitors_keep_the_last_known_result(): void
    {
        $paused = $this->source('uptimerobot', 'Paused', ['enabled' => false]);
        $failed = $this->source('uptimerobot', 'Failed', ['consecutive_failures' => 1]);
        $stale = $this->source('uptimerobot', 'Stale', ['latest_data_date' => '2026-11-04']);
        $pending = $this->source('uptimerobot', 'Pending', ['last_success_at' => null]);
        $this->scoped(function () use ($paused, $failed, $stale, $pending) {
            foreach ([$paused, $failed, $pending] as $source) {
                DailyMetric::create([
                    'source_id' => $source->id, 'metric' => 'monitor_status', 'date' => '2026-11-09', 'value' => 8.0,
                    'is_partial' => false, 'collected_at' => CarbonImmutable::now('UTC'), 'window_end' => CarbonImmutable::now('UTC'),
                ]);
            }
            DailyMetric::create([
                'source_id' => $stale->id, 'metric' => 'monitor_status', 'date' => '2026-11-04', 'value' => 9.0,
                'is_partial' => false, 'collected_at' => CarbonImmutable::parse('2026-11-04 15:00:00', 'UTC'),
                'window_end' => CarbonImmutable::parse('2026-11-04 15:00:00', 'UTC'),
            ]);
        });

        $sources = $this->scoped(fn () => app(DashboardData::class)->projects(30, $this->project->id)[0]['sources']);
        $byLabel = collect($sources)->keyBy('label');
        $this->assertFalse($byLabel['Paused']['current_monitor']['incident']);
        $this->assertSame('disabled', $byLabel['Paused']['current_monitor']['withheld']);
        $this->assertSame('failed', $byLabel['Failed']['current_monitor']['withheld']);
        $this->assertSame('stale', $byLabel['Stale']['current_monitor']['withheld']);
        $this->assertSame('pending', $byLabel['Pending']['current_monitor']['withheld']);

        $report = $this->findings(array_map(fn (array $source): array => $source, $sources));
        $this->assertSame([], $report['findings']);
        $this->assertFalse($report['checked']);
        $notes = implode("\n", $report['notes']);
        $this->assertStringContainsString('Last known result for Paused: Seems down', $notes);
        $this->assertStringContainsString('monitor is paused', $notes);
        $this->assertStringContainsString('Last known result for Failed: Seems down', $notes);
        $this->assertStringContainsString('latest check failed', $notes);
        $this->assertStringContainsString('Last known result for Stale: Down', $notes);
        $this->assertStringContainsString('data is stale', $notes);
        $this->assertStringContainsString('Last known result for Pending: Seems down', $notes);
        $this->assertStringContainsString('has not reported a current check', $notes);
    }

    public function test_search_clicks_fall_only_for_a_complete_current_window(): void
    {
        $source = $this->source('gsc', 'Journal');
        $this->clicks($source, $this->window(30, 10));
        $report = $this->findings([$this->gsc($source)]);
        $this->assertTrue($report['checked']);
        $this->assertSame([], $report['notes']);
        $this->assertSame('Search clicks fell on Journal', $report['findings'][0]['title']);
        $this->assertStringContainsString('70 clicks from 3 Nov 2026 to 9 Nov 2026', $report['findings'][0]['evidence']);
        $this->assertStringContainsString('down from 210', $report['findings'][0]['evidence']);
        $this->assertNull($report['findings'][0]['extra']);
        $this->assertSame('chart-'.$source->id.'-clicks', $report['findings'][0]['anchor']);
        $this->assertSame(['from' => '2026-11-03', 'to' => '2026-11-09'], $report['findings'][0]['period']);
        $this->assertSame('Open Search, 3 Nov – 9 Nov 2026', $report['findings'][0]['action']);

        $small = $this->source('gsc', 'Small');
        $this->clicks($small, $this->window(10, 8));
        $belowCount = $this->findings([$this->gsc($small)]);
        $this->assertTrue($belowCount['checked']);
        $this->assertSame([], $belowCount['findings']);

        $shallow = $this->source('gsc', 'Shallow');
        $this->clicks($shallow, $this->window(40, 36));
        $belowRatio = $this->findings([$this->gsc($shallow)]);
        $this->assertTrue($belowRatio['checked']);
        $this->assertSame([], $belowRatio['findings']);

        $zero = $this->source('gsc', 'Zero');
        $this->clicks($zero, $this->window(0, 10));
        $zeroReport = $this->findings([$this->gsc($zero)]);
        $this->assertFalse($zeroReport['checked']);
        $this->assertStringContainsString('previous 7 days (27 Oct – 2 Nov 2026) had no clicks', $zeroReport['notes'][0]);

        $sparse = $this->source('gsc', 'Sparse');
        $values = $this->window(30, 10);
        unset($values['2026-11-04']);
        $this->clicks($sparse, $values);
        $sparseReport = $this->findings([$this->gsc($sparse)]);
        $this->assertSame([], $sparseReport['findings']);
        $this->assertStringContainsString('missing final daily click totals for the 14 days ending 9 Nov 2026', $sparseReport['notes'][0]);

        $partial = $this->source('gsc', 'Partial');
        $this->clicks($partial, $this->window(30, 10), ['2026-11-05']);
        $partialReport = $this->findings([$this->gsc($partial)]);
        $this->assertSame([], $partialReport['findings']);
        $this->assertStringContainsString('missing final daily click totals', $partialReport['notes'][0]);

        $old = $this->source('gsc', 'Old');
        $this->clicks($old, $this->window(30, 10, '2026-10-19'));
        $oldReport = $this->findings([$this->gsc($old)]);
        $this->assertSame([], $oldReport['findings']);
        $this->assertStringContainsString('outside the last 7 days', $oldReport['notes'][0]);

        $empty = $this->source('gsc', 'Empty');
        $emptyReport = $this->findings([$this->gsc($empty)]);
        $this->assertStringContainsString('no final daily click total in the last 7 days', $emptyReport['notes'][0]);

        $failed = $this->findings([$this->gsc($source, ['status' => 'failed', 'enabled' => true])]);
        $this->assertStringContainsString('needs attention', $failed['notes'][0]);
        $paused = $this->findings([$this->gsc($source, ['status' => 'disabled', 'enabled' => false])]);
        $this->assertStringContainsString('is paused', $paused['notes'][0]);
        $pending = $this->findings([$this->gsc($source, ['status' => 'pending'])]);
        $this->assertStringContainsString('still importing', $pending['notes'][0]);
        $delayed = $this->findings([$this->gsc($source, ['status' => 'delayed'])]);
        $this->assertStringContainsString('is delayed', $delayed['notes'][0]);
    }

    public function test_a_partial_latest_day_uses_the_previous_final_day(): void
    {
        $source = $this->source('gsc', 'Journal');
        $values = [];
        $day = CarbonImmutable::parse('2026-10-26');
        for ($i = 0; $i < 15; $i++) {
            $values[$day->addDays($i)->toDateString()] = $i < 7 ? 40.0 : 10.0;
        }
        $this->clicks($source, $values, ['2026-11-09']);

        $finding = $this->findings([$this->gsc($source)])['findings'][0];
        $this->assertSame(['from' => '2026-11-02', 'to' => '2026-11-08'], $finding['period']);
        $this->assertStringContainsString('70 clicks from 2 Nov 2026 to 8 Nov 2026', $finding['evidence']);
        $this->assertStringContainsString('down from 280', $finding['evidence']);
    }

    public function test_a_named_query_loss_is_extra_evidence_and_opens_top_queries(): void
    {
        $source = $this->source('gsc', 'Journal');
        $this->clicks($source, $this->window(30, 10));
        $this->searchQuery($source, 'brand', false, '2026-11-03', '2026-11-09', 40);
        $this->searchQuery($source, 'brand', true, '2026-10-27', '2026-11-02', 80);
        $this->searchQuery($source, 'alpha', false, '2026-11-03', '2026-11-09', 10);
        $this->searchQuery($source, 'alpha', true, '2026-10-27', '2026-11-02', 40);
        $this->searchQuery($source, 'partial query', false, '2026-11-03', '2026-11-09', 10, true);
        $this->searchQuery($source, 'partial query', true, '2026-10-27', '2026-11-02', 80);
        $this->searchQuery($source, 'other window', false, '2026-11-02', '2026-11-08', 10);
        $this->searchQuery($source, 'other window', true, '2026-10-26', '2026-11-01', 80);
        $this->scoped(fn () => ProjectEvent::create([
            'project_id' => $this->project->id, 'origin' => 'github_deployment', 'title' => 'Production deploy',
            'date' => '2026-11-05', 'external_id' => 'deploy-1',
        ]));
        $this->scoped(fn () => ProjectEvent::create([
            'project_id' => $this->project->id, 'origin' => 'manual', 'title' => 'Editorial note',
            'date' => '2026-11-06', 'external_id' => 'note-1',
        ]));

        $finding = $this->findings([$this->gsc($source)])['findings'][0];
        $this->assertSame('“brand” fell from 80 to 40 clicks over those same dates.', $finding['extra']);
        $this->assertSame('queries-search', $finding['anchor']);
        $this->assertSame('Open Top queries, 3 Nov – 9 Nov 2026', $finding['action']);
        $this->assertStringContainsString('Production deploy (5 Nov 2026)', $finding['context']);
        $this->assertStringNotContainsString('Editorial note', $finding['context']);

        $hidden = $this->findings([$this->gsc($source, ['hidden_keys' => ['top_queries']])])['findings'][0];
        $this->assertSame('“brand” fell from 80 to 40 clicks over those same dates.', $hidden['extra']);
        $this->assertSame('chart-'.$source->id.'-clicks', $hidden['anchor']);
        $this->assertSame('Open Search, 3 Nov – 9 Nov 2026', $hidden['action']);

        $chartHidden = $this->findings([$this->gsc($source, ['hidden_keys' => ['clicks']])])['findings'][0];
        $this->assertSame('queries-search', $chartHidden['anchor']);
        $bothHidden = $this->findings([$this->gsc($source, ['hidden_keys' => ['clicks', 'top_queries']])])['findings'][0];
        $this->assertSame('group-search', $bothHidden['anchor']);
    }

    public function test_the_largest_loss_follows_incidents_and_other_workspaces_stay_out(): void
    {
        $smaller = $this->source('gsc', 'Smaller');
        $larger = $this->source('gsc', 'Larger');
        $this->clicks($smaller, $this->window(20, 5));
        $this->clicks($larger, $this->window(50, 10));
        $monitor = [
            'id' => 900, 'kind' => 'uptimerobot', 'label' => 'Workers', 'hidden_keys' => [],
            'current_monitor' => ['value' => 9, 'date' => '2026-11-09', 'checked_at' => '2026-11-09T15:00:00+00:00', 'incident' => true, 'withheld' => null],
        ];
        $second = $monitor;
        $second['id'] = 901;
        $second['label'] = 'API';
        $second['current_monitor']['value'] = 8;
        $report = $this->findings([$monitor, $second, $this->gsc($smaller), $this->gsc($larger)]);
        $this->assertSame('Workers: Down. API: Seems down.', $report['findings'][0]['title']);
        $this->assertSame('group-reliability', $report['findings'][0]['anchor']);
        $this->assertSame('Search clicks fell on Larger', $report['findings'][1]['title']);
        $this->assertCount(2, $report['findings']);

        $other = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Other', 'slug' => 'other']);
        $foreign = $this->scoped(function () {
            $project = Project::create(['name' => 'Other', 'slug' => 'other']);

            return Source::create([
                'project_id' => $project->id, 'kind' => 'gsc', 'label' => 'Foreign', 'config' => [],
                'identity_hash' => hash('sha256', 'foreign'), 'reporting_timezone' => 'UTC',
                'last_success_at' => CarbonImmutable::now('UTC'), 'latest_data_date' => '2026-11-09',
            ]);
        }, $other);
        $this->scoped(function () use ($foreign) {
            foreach ($this->window(80, 10) as $date => $value) {
                DailyMetric::create([
                    'source_id' => $foreign->id, 'metric' => 'clicks', 'date' => $date, 'value' => $value,
                    'is_partial' => false, 'collected_at' => CarbonImmutable::now('UTC'),
                ]);
            }
        }, $other);

        $isolated = $this->findings([$this->gsc($foreign)]);
        $this->assertSame([], $isolated['findings']);
        $this->assertStringContainsString('no final daily click total', $isolated['notes'][0]);
    }

    public function test_a_project_without_either_source_says_what_can_be_checked(): void
    {
        $source = $this->source('umami', 'Website');
        $report = $this->findings([['id' => $source->id, 'kind' => 'umami', 'label' => 'Website', 'enabled' => true, 'status' => 'healthy']]);
        $this->assertFalse($report['checked']);
        $this->assertSame([], $report['findings']);
        $this->assertStringContainsString('Connect one of those sources', $report['notes'][0]);
    }

    public function test_the_overview_renders_a_qualifying_search_finding(): void
    {
        $source = $this->source('gsc', 'Journal');
        $this->clicks($source, $this->window(30, 10));

        $this->actingAs($this->owner)->get('/w/findings/projects/'.$this->project->id.'?view=overview&days=90')->assertOk()
            ->assertSee('Search clicks fell on Journal')
            ->assertSee('Google Search Console recorded 70 clicks from 3 Nov 2026 to 9 Nov 2026')
            ->assertSee('Open Search, 3 Nov – 9 Nov 2026')
            ->assertSee('from=2026-11-03&amp;to=2026-11-09#chart-'.$source->id.'-clicks', false)
            ->assertDontSee('data-metric-chart', false);
    }

    private function searchQuery(Source $source, string $text, bool $previous, string $start, string $end, float $clicks, bool $partial = false): void
    {
        $this->scoped(fn () => SearchQuery::create([
            'source_id' => $source->id, 'days' => 7, 'previous' => $previous, 'start_date' => $start, 'end_date' => $end,
            'query' => $text, 'clicks' => $clicks, 'impressions' => 100, 'position' => 5, 'is_partial' => $partial,
            'collected_at' => CarbonImmutable::now('UTC'),
        ]));
    }
}
