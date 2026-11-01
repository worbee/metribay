<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\MetricStore;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class MetricStorageTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'Metrics', 'slug' => 'metrics']);
    }

    private function runScoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback(...));
    }

    private function source(string $kind = 'wporg'): Source
    {
        $p = Project::create(['name' => 'Example', 'slug' => 'example']);

        return Source::create(['project_id' => $p->id, 'kind' => $kind, 'label' => 'Metrics', 'config' => [], 'identity_hash' => str_repeat('a', 64), 'reporting_timezone' => 'UTC']);
    }

    public function test_idempotent_revisions_keep_first_capture_and_update_finality(): void
    {
        $this->runScoped(function () {
            $source = $this->source();
            $first = CarbonImmutable::parse('2026-10-24T12:00:00Z');
            $next = $first->addDay();
            $store = app(MetricStore::class);
            $store->persist($source, new CollectionResult([['metric' => 'downloads', 'date' => '2026-10-24', 'value' => 4.0, 'is_partial' => true]]), $first);
            $store->persist($source, new CollectionResult([['metric' => 'downloads', 'date' => '2026-10-24', 'value' => 4.0, 'is_partial' => false]]), $next);
            $row = DailyMetric::first();
            $this->assertSame(1, DailyMetric::count());
            $this->assertFalse($row->is_partial);
            $this->assertTrue($row->collected_at->equalTo($first));
            $this->assertNull($row->revised_at);
            $store->persist($source, new CollectionResult([['metric' => 'downloads', 'date' => '2026-10-24', 'value' => 7.0, 'is_partial' => false]]), $next);
            $row = $row->fresh();
            $this->assertTrue($row->collected_at->equalTo($first));
            $this->assertTrue($row->revised_at->equalTo($next));
        });
    }

    public function test_invalid_result_does_not_partially_write(): void
    {
        $this->runScoped(function () {
            $source = $this->source();
            try {
                app(MetricStore::class)->persist($source, new CollectionResult([['metric' => 'downloads', 'date' => '2026-10-24', 'value' => 4.0, 'is_partial' => false], ['metric' => 'downloads', 'date' => '2026-02-31', 'value' => 2.0, 'is_partial' => false]]), CarbonImmutable::now());
                $this->fail('Invalid dates must fail.');
            } catch (\InvalidArgumentException) {
            }$this->assertSame(0, DailyMetric::count());
        });
    }

    public function test_infinite_and_negative_values_are_rejected(): void
    {
        $this->runScoped(function () {
            $source = $this->source();
            foreach ([INF, NAN, -1.0] as $value) {
                try {
                    app(MetricStore::class)->persist($source, new CollectionResult([['metric' => 'downloads', 'date' => '2026-10-24', 'value' => $value, 'is_partial' => false]]), CarbonImmutable::now());
                    $this->fail('Invalid values must fail.');
                } catch (\InvalidArgumentException) {
                }
            }$this->assertSame(0, DailyMetric::count());
        });
    }

    public function test_snapshot_replacement_removes_only_omitted_observations(): void
    {
        $this->runScoped(function () {
            $source = $this->source('uptimerobot');
            $now = CarbonImmutable::now();
            $date = $now->toDateString();
            $store = app(MetricStore::class);
            $store->persist($source, new CollectionResult([['metric' => 'monitor_status', 'date' => $date, 'value' => 2.0, 'is_partial' => false], ['metric' => 'uptime_24h', 'date' => $date, 'value' => 99.9, 'is_partial' => false]]), $now);
            $store->persist($source, new CollectionResult(daily: [['metric' => 'monitor_status', 'date' => $date, 'value' => 0.0, 'is_partial' => false]], replaceDailyDates: [$date => ['monitor_status', 'uptime_24h', 'response_time_24h']]), $now->addHour());
            $this->assertSame(1, DailyMetric::count());
            $this->assertSame(0.0, DailyMetric::first()->value);
        });
    }

    public function test_daily_visitors_are_not_summed_for_headline_and_gaps_remain_unknown(): void
    {
        $this->runScoped(function () {
            $source = $this->source('umami');
            $today = CarbonImmutable::now('UTC')->toDateString();
            app(MetricStore::class)->persist($source, new CollectionResult([['metric' => 'visitors', 'date' => $today, 'value' => 3.0, 'is_partial' => false]]), CarbonImmutable::now());
            $metric = collect(app(DashboardData::class)->projects(7)[0]['sources'][0]['metrics'])->firstWhere('key', 'visitors');
            $this->assertNull($metric['value']);
            $this->assertCount(7, $metric['series']);
            $this->assertSame(6, count(array_filter($metric['series'], fn ($point) => $point['value'] === null)));
        });
    }

    public function test_independent_period_total_is_used_exactly(): void
    {
        $this->runScoped(function () {
            $source = $this->source('umami');
            $end = CarbonImmutable::now('UTC')->startOfDay();
            app(MetricStore::class)->persist($source, new CollectionResult(periods: [['metric' => 'visitors', 'start_date' => $end->subDays(6)->toDateString(), 'end_date' => $end->toDateString(), 'value' => 8.0, 'is_partial' => true]]), $end);
            $metric = collect(app(DashboardData::class)->projects(7)[0]['sources'][0]['metrics'])->firstWhere('key', 'visitors');
            $this->assertSame(8.0, $metric['value']);
            $this->assertTrue($metric['partial']);
        });
    }

    public function test_first_collection_failure_is_visible_before_any_success(): void
    {
        $this->runScoped(function () {
            $source = $this->source();
            $source->update(['consecutive_failures' => 1, 'error_code' => 'ConnectorException']);
            $this->assertSame('failed', app(DashboardData::class)->projects(7)[0]['sources'][0]['status']);
        });
    }

    public function test_issue_snapshots_update_today_without_overwriting_history_or_filling_gaps(): void
    {
        $this->runScoped(function () {
            $source = $this->source('github');
            $now = CarbonImmutable::now('UTC')->startOfDay()->addHours(12);
            $store = app(MetricStore::class);
            foreach ([[$now->subDays(2), 7.0], [$now, 4.0], [$now->addMinute(), 0.0]] as [$observed, $count]) {
                $date = $observed->toDateString();
                $store->persist($source, new CollectionResult(daily: [['metric' => 'open_issues', 'date' => $date, 'value' => $count, 'is_partial' => false]], replaceDailyDates: [$date => ['open_issues']]), $observed);
            }
            $this->assertSame(2, DailyMetric::count());
            $metric = collect(app(DashboardData::class)->projects(7)[0]['sources'][0]['metrics'])->firstWhere('key', 'open_issues');
            $this->assertSame(0.0, $metric['value']);
            $this->assertSame([null, null, null, null, 7.0, null, 0.0], array_column($metric['series'], 'value'));
            $this->assertNull($metric['change']);
            $this->assertFalse($metric['partial']);
            $this->assertSame($now->toDateString(), $metric['observed_date']);
        });
    }
}
