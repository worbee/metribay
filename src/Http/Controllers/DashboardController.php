<?php

namespace Metribay\Core\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\WorkspaceContext;

class DashboardController
{
    public function home(Request $request): RedirectResponse
    {
        $workspace = app(WorkspaceAccess::class)->queryFor($request->user())->orderBy('id')->firstOrFail();

        return redirect()->route('metribay.dashboard', ['workspace' => $workspace->slug]);
    }

    public function index(Request $request): View
    {
        $days = $this->days($request);

        return view('metribay::dashboard', ['workspace' => app(WorkspaceContext::class)->current(), 'days' => $days]);
    }

    public function project(Request $request, DashboardData $data): View
    {
        $days = $this->days($request);
        $project = Project::query()->whereKey($request->route('project'))->firstOrFail();
        $projects = $data->projects($days, $project->id);

        $sources = $projects[0]['sources'] ?? [];
        $events = ProjectEvent::query()->where('project_id', $project->id)->orderByDesc('date')->orderByDesc('id')->get();
        foreach ($sources as &$source) {
            $range = $source['metrics'][0] ?? null;
            $source['events'] = $range ? $this->chartEvents($events, (int) $source['id'], CarbonImmutable::parse($range['start_date']), CarbonImmutable::parse($range['end_date'])) : [];
        }
        unset($source);

        return view('metribay::project', ['workspace' => app(WorkspaceContext::class)->current(), 'project' => $project, 'sources' => $sources, 'events' => $events, 'days' => $days]);
    }

    /**
     * Event markers for one data source's charts: whole-project events plus its own.
     *
     * @param  iterable<ProjectEvent>  $events
     * @return list<array{date: string, title: string, color: string, automatic: bool}>
     */
    private function chartEvents(iterable $events, int $sourceId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $markers = [];
        foreach ($events as $event) {
            if ($event->source_id !== null && (int) $event->source_id !== $sourceId) {
                continue;
            }
            foreach ($event->occurrencesBetween($from, $to) as $date) {
                $markers[] = ['date' => $date, 'title' => $event->title, 'color' => $event->displayColor(), 'automatic' => ! $event->isCustom()];
            }
        }
        usort($markers, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $markers;
    }

    public function profile(Request $request): View
    {
        abort_unless(config('metribay.account_enabled', true), 404);

        return view('metribay::profile', ['user' => $request->user()]);
    }

    private function days(Request $request): int
    {
        $days = (int) $request->query('days', 30);
        abort_unless(in_array($days, [7, 30, 90], true), 422);

        return $days;
    }
}
