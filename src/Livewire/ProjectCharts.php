<?php

namespace Metribay\Core\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Metribay\Core\Contracts\WorkspaceAccess;
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

    public function render(): View
    {
        abort_unless(auth()->check(), 401);
        $workspace = Workspace::query()->findOrFail($this->workspaceId);
        abort_unless(app(WorkspaceAccess::class)->allows(auth()->user(), $workspace, 'view'), 404);
        abort_unless(in_array($this->days, [7, 30, 90], true), 422);
        request()->attributes->set('metribay.workspace', $workspace);

        return app(WorkspaceContext::class)->run($workspace, function () use ($workspace) {
            $project = Project::query()->findOrFail($this->projectId);
            $data = app(DashboardData::class);
            $projectData = $data->projects($this->days, $project->id)[0] ?? ['sources' => [], 'summary' => []];
            $sources = $projectData['sources'];
            $events = ProjectEvent::query()->where('project_id', $project->id)->orderByDesc('date')->orderByDesc('id')->get();
            $hidden = HiddenChart::query()->where('project_id', $project->id)->get();
            foreach ($sources as &$source) {
                $range = $source['metrics'][0] ?? null;
                $source['events'] = $range ? $this->chartEvents($events, (int) $source['id'], CarbonImmutable::parse($range['start_date']), CarbonImmutable::parse($range['end_date'])) : [];
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
            $queries = app(SearchQueries::class)->forSources($sources, $this->days);
            foreach ($sources as &$source) {
                $source['queries'] = in_array(SearchQueries::CHART_KEY, $source['hidden_keys'], true) ? null : ($queries[(int) $source['id']] ?? null);
            }
            unset($source);

            $today = CarbonImmutable::now('UTC')->startOfDay();
            $eventCount = $events->sum(fn (ProjectEvent $event): int => count($event->occurrencesBetween($today->subDays($this->days - 1), $today)));
            $statuses = array_count_values(array_column($sources, 'status'));
            $lastSync = max([0, ...array_filter(array_column($sources, 'last_success_timestamp'))]);

            return view('metribay::components.project-charts', ['workspace' => $workspace, 'project' => $project, 'sources' => $sources, 'rows' => ProjectLayout::rows($sources), 'summary' => $projectData['summary'], 'statuses' => $statuses, 'lastSync' => $lastSync ? CarbonImmutable::createFromTimestampUTC($lastSync) : null, 'days' => $this->days, 'hiddenCount' => $hidden->count(), 'justHidden' => $hidden->firstWhere('id', (int) session('chart_hidden')), 'eventCount' => $eventCount]);
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
