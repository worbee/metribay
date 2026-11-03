<?php

namespace Metribay\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Metribay\Core\Contracts\WorkspaceAccess;
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
        $days = $this->days($request);

        return view('metribay::dashboard', ['workspace' => app(WorkspaceContext::class)->current(), 'days' => $days]);
    }

    public function project(Request $request): View
    {
        $days = $this->days($request);
        $project = Project::query()->whereKey($request->route('project'))->firstOrFail();

        return view('metribay::project', ['workspace' => app(WorkspaceContext::class)->current(), 'project' => $project, 'sources' => $project->sources->map(fn ($source) => ['id' => $source->id, 'label' => $source->label])->all(), 'days' => $days]);
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
