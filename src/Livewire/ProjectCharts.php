<?php

namespace Metribay\Core\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Metrics\Period;
use Metribay\Core\Metrics\ProjectFindings;
use Metribay\Core\Metrics\ProjectLayout;
use Metribay\Core\Models\HiddenChart;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\SearchQueries;
use Metribay\Core\Services\WorkspaceContext;

class ProjectCharts extends Component
{
    #[Locked]
    public int $workspaceId;

    #[Locked]
    public int $projectId;

    #[Locked]
    public int $days = 30;

    #[Locked]
    public string $from = '';

    #[Locked]
    public string $to = '';

    /** "overview" or empty. Empty and anything else open All metrics. */
    #[Url(as: 'view', except: '')]
    public string $view = '';

    /** Archived month (Y-m) shown by Top queries instead of the period; empty for the period. */
    #[Url(as: 'queries', except: '')]
    public string $queryMonth = '';

    /** Month (Y-m) the archived month is compared with, "none", or empty for the month before. */
    #[Url(as: 'compare', except: '')]
    public string $queryCompare = '';

    #[Locked]
    public bool $monthChecked = false;

    public function render(): View
    {
        abort_unless(auth()->check(), 401);
        $workspace = Workspace::query()->findOrFail($this->workspaceId);
        abort_unless(app(WorkspaceAccess::class)->allows(auth()->user(), $workspace, 'view'), 404);
        $period = Period::fromInput($this->days, $this->from, $this->to);
        abort_if($period === null, 422);
        request()->attributes->set('metribay.workspace', $workspace);

        return app(WorkspaceContext::class)->run($workspace, function () use ($workspace, $period) {
            $project = Project::query()->findOrFail($this->projectId);
            $projectView = $this->view === 'overview' ? 'overview' : 'metrics';
            $metricsView = $projectView === 'metrics';
            $data = app(DashboardData::class);
            $projectData = $data->projects($period, $project->id)[0] ?? ['sources' => [], 'summary' => []];
            $sources = $projectData['sources'];
            $events = ProjectEvent::query()->where('project_id', $project->id)->orderByDesc('date')->orderByDesc('id')->get();
            $hidden = HiddenChart::query()->where('project_id', $project->id)->get();
            foreach ($sources as &$source) {
                $range = $source['metrics'][0] ?? null;
                // Chart markers are part of All metrics. Overview keeps the event count in the header only.
                $source['events'] = $metricsView && $range ? $this->chartEvents($events, (int) $source['id'], CarbonImmutable::parse($range['start_date']), CarbonImmutable::parse($range['end_date'])) : [];
                // Hidden charts leave the page only; context such as page speed test details still reads every metric.
                $hiddenKeys = $hidden->where('source_id', (int) $source['id'])->pluck('metric_key')->all();
                $source['all_metrics'] = $source['metrics'];
                $source['metrics'] = array_values(array_filter($source['metrics'], fn (array $metric): bool => ! in_array($metric['key'], $hiddenKeys, true)));
                $source['hidden_keys'] = $hiddenKeys;
                $source['hidden_count'] = count($hiddenKeys);
                $monitor = null;
                foreach ($source['metrics'] as $metric) {
                    if ($metric['key'] === 'monitor_status') {
                        $monitor = $metric;
                        break;
                    }
                }
                $source['attention_anchor'] = empty($source['error_code']) && in_array($monitor['value'] ?? null, [8, 9, 8.0, 9.0], true)
                    ? 'chart-'.$source['id'].'-monitor_status'
                    : 'source-attention-'.$source['id'];
            }
            unset($source);
            $searchQueries = app(SearchQueries::class);
            $queryIds = array_map(fn (array $source): int => (int) $source['id'], array_filter($sources, fn (array $source): bool => ! in_array(SearchQueries::CHART_KEY, $source['hidden_keys'], true)));
            $months = $searchQueries->months(array_values($queryIds));
            // Unknown or removed months fall back to the period instead of an error.
            // A custom range that is exactly an archived month opens on that month, once; the menu can still leave it.
            if ($metricsView && ! $this->monthChecked && $this->queryMonth === '' && in_array($period->calendarMonth(), $months, true)) {
                $this->queryMonth = (string) $period->calendarMonth();
            }
            if ($metricsView) {
                $this->monthChecked = true;
            }
            $month = in_array($this->queryMonth, $months, true) ? $this->queryMonth : null;
            $compare = $month !== null && ($this->queryCompare === 'none' || (in_array($this->queryCompare, $months, true) && $this->queryCompare !== $month)) ? $this->queryCompare : null;
            $queries = $metricsView ? $searchQueries->forSources($sources, $period, $month, $compare) : [];
            foreach ($sources as &$source) {
                $source['queries'] = in_array((int) $source['id'], $queryIds, true) ? ($queries[(int) $source['id']] ?? null) : null;
            }
            unset($source);
            $rows = $metricsView ? ProjectLayout::rows($sources, $period->days === 1) : [];
            $linked = [];
            foreach (array_keys($projectData['summary']) as $column) {
                $group = ProjectLayout::SUMMARY[$column] ?? null;
                if ($group !== null) {
                    $linked[$group] = true;
                }
            }
            $unlinkedGroups = [];
            foreach ($rows as $row) {
                foreach ($row as $group) {
                    if (! isset($linked[$group['key']])) {
                        $unlinkedGroups[] = ['key' => $group['key'], 'title' => $group['title']];
                    }
                }
            }
            $report = ! $metricsView && $sources !== [] ? app(ProjectFindings::class)->forSources($sources, $project->id) : ['findings' => [], 'checked' => false, 'notes' => []];

            [$eventsFrom, $eventsTo] = $period->window('UTC', CarbonImmutable::now('UTC'));
            $eventCount = $events->sum(fn (ProjectEvent $event): int => count($event->occurrencesBetween($eventsFrom, $eventsTo)));
            $statuses = array_count_values(array_column($sources, 'status'));
            $lastSync = max([0, ...array_filter(array_column($sources, 'last_success_timestamp'))]);

            return view('metribay::components.project-charts', [
                'workspace' => $workspace, 'project' => $project, 'sources' => $sources, 'rows' => $rows, 'summary' => $projectData['summary'], 'statuses' => $statuses, 'lastSync' => $lastSync ? CarbonImmutable::createFromTimestampUTC($lastSync) : null, 'days' => $period->days, 'period' => $period, 'projectView' => $projectView,
                'queryMonths' => $months, 'queryMonth' => $month, 'queryCompare' => $compare, 'hiddenCount' => $hidden->count(), 'justHidden' => $hidden->firstWhere('id', (int) session('chart_hidden')), 'eventCount' => $eventCount,
                'findings' => $report['findings'], 'findingsChecked' => $report['checked'], 'findingNotes' => $report['notes'], 'unlinkedGroups' => $unlinkedGroups,
            ]);
        });
    }

    /**
     * Event markers for one data source's charts: whole-project events plus its own.
     *
     * @param  iterable<ProjectEvent>  $events
     * @return list<array{date: string, title: string, color: string, automatic: bool, anchor: string}>
     */
    private function chartEvents(iterable $events, int $sourceId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $markers = [];
        foreach ($events as $event) {
            if ($event->source_id !== null && (int) $event->source_id !== $sourceId) {
                continue;
            }
            foreach ($event->occurrencesBetween($from, $to) as $date) {
                // Where the marker opens on the events page.
                $markers[] = ['date' => $date, 'title' => $event->title, 'color' => $event->displayColor(), 'automatic' => ! $event->isCustom(), 'anchor' => $event->isCustom() && $event->recurrence ? 'occurrence-'.$event->id.'-'.$date : 'event-'.$event->id];
            }
        }
        usort($markers, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $markers;
    }
}
