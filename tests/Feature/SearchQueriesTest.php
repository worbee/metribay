<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Metrics\Period;
use Metribay\Core\Metrics\ProjectLayout;
use Metribay\Core\Models\HiddenChart;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\SearchQuery;
use Metribay\Core\Models\SearchQueryMonth;
use Metribay\Core\Models\SearchQueryMonthRow;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\MetricStore;
use Metribay\Core\Services\ProjectTransfer;
use Metribay\Core\Services\SearchQueries;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class SearchQueriesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Search', 'slug' => 'search']);
        $this->project = $this->scoped(fn () => Project::create(['name' => 'Search', 'slug' => 'search']));
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback);
    }

    private function source(string $kind = 'gsc', string $label = 'Search'): Source
    {
        return $this->scoped(fn () => Source::create(['project_id' => $this->project->id, 'kind' => $kind, 'label' => $label, 'config' => [], 'identity_hash' => hash('sha256', $kind.$label),
            'reporting_timezone' => $kind === 'gsc' ? 'America/Los_Angeles' : 'UTC', 'last_success_at' => CarbonImmutable::now('UTC')]));
    }

    /** @return array<string, mixed> */
    private function row(string $query, float $clicks, float $impressions, ?float $position, bool $previous = false, int $days = 30): array
    {
        $end = CarbonImmutable::parse('2026-10-31');

        return ['days' => $days, 'previous' => $previous, 'start_date' => ($previous ? $end->subDays(2 * $days - 1) : $end->subDays($days - 1))->toDateString(),
            'end_date' => ($previous ? $end->subDays($days) : $end)->toDateString(), 'query' => $query, 'clicks' => $clicks, 'impressions' => $impressions, 'position' => $position, 'is_partial' => false];
    }

    /** @param list<array<string, mixed>> $queries @param list<array<string, mixed>> $daily */
    private function persist(Source $source, ?array $queries, array $daily = []): void
    {
        $this->scoped(fn () => app(MetricStore::class)->persist($source, new CollectionResult(daily: $daily, queries: $queries), CarbonImmutable::now('UTC')));
    }

    /** Thirty recorded days with 10 clicks and 200 impressions each: a site click-through rate of 5%. */
    private function siteTotals(): array
    {
        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $date = CarbonImmutable::parse('2026-10-31')->subDays($i)->toDateString();
            $rows[] = ['metric' => 'clicks', 'date' => $date, 'value' => 10.0, 'is_partial' => false];
            $rows[] = ['metric' => 'impressions', 'date' => $date, 'value' => 200.0, 'is_partial' => false];
        }

        return $rows;
    }

    public function test_queries_are_replaced_as_one_snapshot_and_left_alone_without_a_list(): void
    {
        $source = $this->source();
        $this->persist($source, [$this->row('old', 1, 2, 3.0), $this->row('old', 1, 2, 3.0, true)]);
        $this->persist($source, [$this->row('new', 4, 5, 6.0)]);
        $this->assertSame(['new'], $this->scoped(fn () => SearchQuery::query()->pluck('query')->all()));
        $this->persist($source, null);
        $this->assertSame(1, $this->scoped(fn () => SearchQuery::query()->count()));
        $this->persist($source, []);
        $this->assertSame(0, $this->scoped(fn () => SearchQuery::query()->count()));
    }

    public function test_only_search_providers_store_valid_queries(): void
    {
        $search = $this->source();
        foreach ([[$this->source('umami', 'Analytics'), $this->row('q', 1, 1, 1.0)], [$search, $this->row('q', 1, 1, 0.0)],
            [$search, $this->row('q', -1, 1, 1.0)], [$search, ['days' => 14] + $this->row('q', 1, 1, 1.0)], [$search, $this->row(' ', 1, 1, 1.0)]] as [$source, $row]) {
            try {
                $this->persist($source, [$row]);
                $this->fail('Expected an invalid query to be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertSame(0, $this->scoped(fn () => SearchQuery::query()->count()));
            }
        }
    }

    public function test_reports_split_clicked_ranked_and_opportunities(): void
    {
        $source = $this->source();
        $this->persist($source, [
            $this->row('metribay', 30, 100, 1.4), $this->row('metribay', 20, 90, 1.9, true),
            $this->row('dashboard tool', 2, 400, 7.6), $this->row('dashboard tool', 2, 300, 11.0, true),
            $this->row('seen rarely', 0, 9, 8.0),
            $this->row('far down', 0, 500, 31.0),
            $this->row('well clicked', 30, 200, 6.0),
            $this->row('brand new', 1, 50, 12.0),
        ], $this->siteTotals());
        $report = $this->scoped(fn () => app(SearchQueries::class)->forSources([['id' => $source->id, 'kind' => 'gsc']], 30))[$source->id];

        // Equal clicks: more impressions first.
        $this->assertSame(['well clicked', 'metribay', 'dashboard tool', 'brand new'], array_column($report['clicked'], 'query'));
        $this->assertSame(['far down', 'dashboard tool', 'well clicked', 'metribay', 'brand new', 'seen rarely'], array_column($report['ranked'], 'query'));
        // Positions 4–20, at least ten impressions and a click-through rate below the site's 5%.
        $this->assertSame(['dashboard tool', 'brand new'], array_column($report['opportunities'], 'query'));
        $this->assertEqualsWithDelta(0.05, $report['site_ctr'], 0.0001);
        $metribay = $report['clicked'][1];
        $this->assertEqualsWithDelta(50.0, $metribay['change'], 0.001);
        $this->assertEqualsWithDelta(0.5, $metribay['position_change'], 0.001);
        $this->assertTrue($report['clicked'][3]['new']);
        $this->assertFalse($report['clicked'][1]['new']);
        // 63 of the site's 300 clicks come from listed queries.
        $this->assertEqualsWithDelta(0.21, $report['coverage'], 0.001);
        $this->assertSame(['2026-10-02', '2026-10-31'], [$report['start_date'], $report['end_date']]);
        $this->assertSame([], $this->scoped(fn () => app(SearchQueries::class)->forSources([['id' => $source->id, 'kind' => 'gsc']], 7)));
    }

    public function test_project_page_shows_top_queries_in_the_search_group(): void
    {
        $source = $this->source();
        $rows = [$this->row('dashboard tool', 2, 400, 7.6), $this->row('dashboard tool', 2, 300, 11.0, true)];
        for ($i = 1; $i <= 12; $i++) {
            $rows[] = $this->row('query '.$i, $i, 100 + $i, 2.0);
        }
        $this->persist($source, $rows, $this->siteTotals());

        $html = $this->actingAs($this->owner)->get('/w/search/projects/'.$this->project->id)->assertOk()
            ->assertSeeInOrder(['id="group-search-heading"', 'Top queries', 'Clicked', 'Ranked', 'Opportunities'], false)
            ->assertSee('data-query-view="opportunities"', false)->assertSee('2 Oct – 31 Oct 2026')
            ->assertSee('Show all 13')->assertSee('below this site’s average of 5%')
            ->assertSee('cell-change is-good', false)
            ->getContent();
        // Ten rows are shown per list; the rest wait for "Show all".
        preg_match('/data-query-panel="clicked".*?<\/tbody>/s', $html, $clicked);
        $this->assertSame(3, substr_count($clicked[0], 'data-query-extra'));
        $this->assertStringContainsString('id="chart-'.$source->id.'-top_queries-label"', $html);

        $this->owner->forceFill(['locale' => 'de'])->save();
        $this->get('/w/search/projects/'.$this->project->id)->assertOk()->assertSee('Top-Suchanfragen')->assertSee('Chancen');
    }

    public function test_top_queries_can_be_hidden_and_restored_like_a_chart(): void
    {
        $source = $this->source();
        $this->persist($source, [$this->row('metribay', 3, 10, 1.0)]);
        $this->actingAs($this->owner)->from('/w/search/projects/'.$this->project->id)
            ->post('/w/search/projects/'.$this->project->id.'/hidden-charts', ['source_id' => $source->id, 'metric_key' => SearchQueries::CHART_KEY])->assertRedirect();
        $this->get('/w/search/projects/'.$this->project->id)->assertOk()->assertDontSee('data-query-block', false)->assertSee('Top queries chart hidden from this project.');
        $this->get('/w/search/projects/'.$this->project->id.'/settings')->assertOk()->assertSee('Top queries');
        $chart = $this->scoped(fn () => HiddenChart::query()->firstOrFail());
        $this->delete('/w/search/projects/'.$this->project->id.'/hidden-charts/'.$chart->id)->assertRedirect();
        $this->get('/w/search/projects/'.$this->project->id)->assertSee('data-query-block', false);
    }

    public function test_search_group_with_queries_takes_the_full_width(): void
    {
        $search = ['id' => 1, 'label' => 'Search', 'kind' => 'gsc', 'status' => 'healthy', 'enabled' => true, 'last_success_at' => 'now',
            'metrics' => [['key' => 'clicks', 'label' => 'Clicks', 'unit' => 'count', 'value' => 1.0, 'series' => [['date' => '2026-11-01', 'value' => 1.0, 'partial' => false]], 'previous_series' => []]]];
        $downloads = ['kind' => 'wporg', 'id' => 2, 'metrics' => [['key' => 'downloads'] + array_slice($search['metrics'][0], 1)]] + $search;
        $this->assertCount(1, ProjectLayout::rows([$search, $downloads]));
        $this->assertCount(2, ProjectLayout::rows([$search + ['queries' => ['count' => 1]], $downloads]));
    }

    /** @param list<array{0: string, 1: float, 2: float, 3: float|null}> $rows */
    private function month(string $month, array $rows, ?int $weeks = null): array
    {
        $start = CarbonImmutable::parse($month.'-01');

        return ['month' => $start->toDateString(), 'start_date' => $start->toDateString(), 'end_date' => $start->endOfMonth()->toDateString(), 'weeks' => $weeks,
            'rows' => array_map(fn (array $row): array => ['query' => $row[0], 'clicks' => $row[1], 'impressions' => $row[2], 'position' => $row[3]], $rows)];
    }

    /** @param list<array<string, mixed>> $months */
    private function archive(Source $source, array $months): void
    {
        $this->scoped(fn () => app(MetricStore::class)->persist($source, new CollectionResult(queryMonths: $months), CarbonImmutable::now('UTC')));
    }

    public function test_months_are_archived_once_and_kept_when_queries_are_replaced(): void
    {
        $source = $this->source();
        $this->archive($source, [$this->month('2026-09', [['metribay', 9, 90, 3.0], ['dashboard', 1, 40, 8.0]]), $this->month('2026-08', [])]);
        // A later collection never replaces an archived month, and replacing the current queries leaves the archive alone.
        $this->archive($source, [$this->month('2026-09', [['other', 1, 1, 1.0]])]);
        $this->persist($source, []);
        $months = $this->scoped(fn () => SearchQueryMonth::query()->orderBy('month')->get());
        $this->assertSame(['2026-08', '2026-09'], $months->map(fn (SearchQueryMonth $month): string => $month->month->format('Y-m'))->all());
        $this->assertSame([0, 2], $months->pluck('query_count')->all());
        $this->assertSame(['metribay', 'dashboard'], $months[1]->rows()->orderBy('id')->pluck('query')->all());
        $this->assertSame(['2026-08', '2026-09'], $this->scoped(fn () => array_reverse(app(SearchQueries::class)->months([$source->id]))));
    }

    public function test_only_search_providers_archive_valid_months(): void
    {
        $search = $this->source();
        $invalid = [
            [$this->source('umami', 'Analytics'), $this->month('2026-09', [['q', 1, 1, 1.0]])],
            [$search, ['month' => '2026-09-02'] + $this->month('2026-09', [])],
            [$search, ['end_date' => '2026-10-01'] + $this->month('2026-09', [])],
            [$search, ['weeks' => 9] + $this->month('2026-09', [])],
            [$search, $this->month('2026-09', [['q', 1, 1, 1.0], ['q', 2, 2, 2.0]])],
            [$search, $this->month('2026-09', [['q', -1, 1, 1.0]])],
        ];
        foreach ($invalid as [$source, $month]) {
            try {
                $this->archive($source, [$month]);
                $this->fail('Expected an invalid month to be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertSame(0, $this->scoped(fn () => SearchQueryMonth::query()->count()));
            }
        }
    }

    public function test_an_archived_month_is_compared_with_the_month_before_or_a_chosen_one(): void
    {
        $source = $this->source();
        $this->archive($source, [
            $this->month('2026-09', [['metribay', 30, 100, 2.0], ['brand new', 2, 50, 9.0]]),
            $this->month('2026-08', [['metribay', 20, 80, 3.0]]),
            $this->month('2026-06', [['metribay', 10, 60, 5.0], ['brand new', 1, 20, 12.0]]),
        ]);
        $sources = [['id' => $source->id, 'kind' => 'gsc']];
        $service = app(SearchQueries::class);
        $report = $this->scoped(fn () => $service->forSources($sources, 30, '2026-09'))[$source->id];
        $this->assertSame(['2026-09', '2026-08', false], [$report['month'], $report['compare'], $report['missing']]);
        $this->assertSame(['2026-09-01', '2026-09-30'], [$report['start_date'], $report['end_date']]);
        $this->assertEqualsWithDelta(50.0, $report['clicked'][0]['change'], 0.001);
        $this->assertEqualsWithDelta(1.0, $report['clicked'][0]['position_change'], 0.001);
        $this->assertTrue($report['clicked'][1]['new']);

        $chosen = $this->scoped(fn () => $service->forSources($sources, 30, '2026-09', '2026-06'))[$source->id];
        $this->assertSame('2026-06', $chosen['compare']);
        $this->assertEqualsWithDelta(200.0, $chosen['clicked'][0]['change'], 0.001);
        $this->assertFalse($chosen['clicked'][1]['new']);

        $none = $this->scoped(fn () => $service->forSources($sources, 30, '2026-09', 'none'))[$source->id];
        $this->assertNull($none['compare']);
        $this->assertNull($none['clicked'][0]['change']);
        // The month before June is not archived: no comparison.
        $this->assertNull($this->scoped(fn () => $service->forSources($sources, 30, '2026-06'))[$source->id]['compare']);
        $this->assertTrue($this->scoped(fn () => $service->forSources($sources, 30, '2026-07'))[$source->id]['missing']);
    }

    public function test_yesterday_and_custom_ranges_show_the_closest_stored_window(): void
    {
        $source = $this->source();
        $this->persist($source, [$this->row('week', 1, 10, 2.0, false, 7), $this->row('month', 2, 20, 2.0), $this->row('quarter', 3, 30, 2.0, false, 90)]);
        $sources = [['id' => $source->id, 'kind' => 'gsc']];
        $report = fn (Period $period): array => $this->scoped(fn () => app(SearchQueries::class)->forSources($sources, $period))[$source->id];
        $this->assertSame(['week', 7, true], [$report(Period::preset(1))['ranked'][0]['query'], $report(Period::preset(1))['days'], $report(Period::preset(1))['fallback']]);
        $this->assertSame('month', $report(Period::custom('2026-10-01', '2026-10-20'))['ranked'][0]['query']);
        $this->assertSame('month', $report(Period::custom('2026-10-01', '2026-11-02'))['ranked'][0]['query']);
        $this->assertSame('quarter', $report(Period::custom('2026-01-01', '2026-10-20'))['ranked'][0]['query']);
        $this->assertFalse($report(Period::preset(30))['fallback']);
    }

    public function test_project_page_offers_archived_months_next_to_the_query_lists(): void
    {
        $source = $this->source();
        $this->persist($source, [$this->row('metribay', 3, 10, 1.0), $this->row('metribay', 1, 4, 1.0, false, 7)], $this->siteTotals());
        $this->archive($source, [$this->month('2026-09', [['metribay', 30, 100, 2.0]]), $this->month('2026-08', [['metribay', 20, 80, 3.0]])]);
        $url = '/w/search/projects/'.$this->project->id;

        $this->actingAs($this->owner)->get($url)->assertOk()
            ->assertSeeInOrder(['Top queries', 'wire:model.live="queryMonth"', 'Last 30 days', 'September 2026', 'August 2026', 'Clicked'], false)
            ->assertDontSee('wire:model.live="queryCompare"', false);
        $this->get($url.'?queries=2026-09')->assertOk()->assertSee('Compared with August 2026')->assertSee('vs August 2026')
            ->assertSee('title="Change from August 2026"', false)->assertSee('wire:model.live="queryCompare"', false);
        // A month that is not archived shows the period.
        $this->get($url.'?queries=2020-01')->assertOk()->assertDontSee('Compared with')->assertSee('wire:model.live="queryMonth"', false);
        // A custom range that is exactly an archived month opens on it.
        $this->get($url.'?from=2026-09-01&to=2026-09-30')->assertOk()->assertSee('Compared with August 2026');
        $this->get($url.'?days=1')->assertOk()->assertSee('this shows the last 7 days', false);
    }

    public function test_queries_move_with_their_project(): void
    {
        $source = $this->source();
        $this->persist($source, [$this->row('metribay', 3, 10, 1.0)]);
        $this->archive($source, [$this->month('2026-09', [['metribay', 30, 100, 2.0]])]);
        $target = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Client', 'slug' => 'client']);
        $moved = app(ProjectTransfer::class)->move($this->workspace, $this->project->id, $target, 1, false);
        $this->assertSame(0, $this->scoped(fn () => SearchQuery::query()->count()));
        $this->assertSame(0, $this->scoped(fn () => SearchQueryMonth::query()->count()));
        $this->assertSame(1, app(WorkspaceContext::class)->run($target, fn () => SearchQueryMonth::query()->whereIn('source_id', Source::query()->where('project_id', $moved['project'])->pluck('id'))->count()));
        $this->assertSame(1, SearchQueryMonthRow::query()->count());
        $this->assertSame(['metribay'], app(WorkspaceContext::class)->run($target, fn () => SearchQuery::query()->whereIn('source_id', Source::query()->where('project_id', $moved['project'])->pluck('id'))->pluck('query')->all()));
    }
}
