<?php

namespace Metribay\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Metrics\Period;
use Metribay\Core\Models\Project;
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
        $period = $this->period($request);

        return view('metribay::dashboard', ['workspace' => app(WorkspaceContext::class)->current(), 'days' => $period->days, 'period' => $period]);
    }

    public function project(Request $request): View
    {
        $period = $this->period($request);
        $project = Project::query()->whereKey($request->route('project'))->firstOrFail();

        return view('metribay::project', ['workspace' => app(WorkspaceContext::class)->current(), 'project' => $project, 'sources' => $project->sources->map(fn ($source) => ['id' => $source->id, 'label' => $source->label])->all(), 'days' => $period->days, 'period' => $period]);
    }

    public function profile(Request $request): View
    {
        abort_unless(config('metribay.account_enabled', true), 404);

        return view('metribay::profile', ['user' => $request->user()]);
    }

    private function period(Request $request): Period
    {
        $period = Period::fromInput($request->query('days', 30), $request->query('from'), $request->query('to'));
        abort_if($period === null, 422);

        return $period;
    }
}
