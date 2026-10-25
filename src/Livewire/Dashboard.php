<?php

namespace Metribay\Core\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Models\ProjectGroup;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\DashboardData;
use Metribay\Core\Services\WorkspaceContext;

class Dashboard extends Component
{
    #[Locked]
    public int $workspaceId;

    #[Url]
    public int $days = 30;

    #[Url(as: 'group')]
    public string $selectedGroup = '';

    public function render(): View
    {
        abort_unless(auth()->check(), 401);
        $workspace = Workspace::query()->findOrFail($this->workspaceId);
        abort_unless(app(WorkspaceAccess::class)->allows(auth()->user(), $workspace, 'view'), 404);
        abort_unless(in_array($this->days, [7, 30, 90], true), 422);
        request()->attributes->set('metribay.workspace', $workspace);

        return app(WorkspaceContext::class)->run($workspace, function () use ($workspace) {
            $projects = app(DashboardData::class)->projects($this->days);
            if ($this->selectedGroup !== '') {
                $projects = array_values(array_filter($projects, fn ($project) => ($project['group'] ?? '') === $this->selectedGroup || ($this->selectedGroup === '__ungrouped' && $project['group'] === null)));
            }

            return view('metribay::components.overview', ['workspace' => $workspace, 'projects' => $projects, 'groups' => ProjectGroup::query()->orderBy('name')->pluck('name')->all(), 'days' => $this->days, 'selectedGroup' => $this->selectedGroup, 'now' => CarbonImmutable::now('UTC')]);
        });
    }
}
