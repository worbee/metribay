<?php

namespace Metribay\Core\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Metrics\Period;
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

    /** First and last date of a custom range; both empty for the last N days or yesterday. */
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    /** Custom range being edited before Apply. */
    public bool $editingRange = false;

    public string $draftFrom = '';

    public string $draftTo = '';

    #[Url(as: 'group')]
    public string $selectedGroup = '';

    #[Url]
    public string $sort = '';

    #[Url(as: 'dir')]
    public string $direction = 'desc';

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['name', ...array_keys(DashboardData::CATEGORIES)], true)) {
            return;
        }
        $this->direction = $this->sort === $column ? ($this->direction === 'asc' ? 'desc' : 'asc') : (in_array($column, ['name', 'speed'], true) ? 'asc' : 'desc');
        $this->sort = $column;
    }

    /** A choice of the period menu: a number of days, or "custom" to edit a range. */
    public function choosePeriod(string $choice): void
    {
        if ($choice === 'custom') {
            $period = Period::fromInput($this->days, $this->from, $this->to) ?? Period::preset(30);
            [$from, $to] = $period->window('UTC', CarbonImmutable::now('UTC'));
            $this->draftFrom = $from->toDateString();
            $this->draftTo = $to->toDateString();
            $this->editingRange = true;

            return;
        }
        if (Period::fromInput($choice) !== null) {
            $this->days = (int) $choice;
            $this->from = $this->to = '';
            $this->editingRange = false;
            $this->resetErrorBag();
        }
    }

    public function applyRange(): void
    {
        $period = Period::custom($this->draftFrom, $this->draftTo);
        if ($period === null) {
            $this->addError('range', __('Choose a start date on or before the end date, up to :days days, ending no later than today.', ['days' => Period::MAX_DAYS]));

            return;
        }
        $this->from = (string) $period->from;
        $this->to = (string) $period->to;
        $this->editingRange = false;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        abort_unless(auth()->check(), 401);
        $workspace = Workspace::query()->findOrFail($this->workspaceId);
        abort_unless(app(WorkspaceAccess::class)->allows(auth()->user(), $workspace, 'view'), 404);
        $period = Period::fromInput($this->days, $this->from, $this->to);
        abort_if($period === null, 422);
        request()->attributes->set('metribay.workspace', $workspace);

        return app(WorkspaceContext::class)->run($workspace, function () use ($workspace, $period) {
            $projects = $this->sorted(app(DashboardData::class)->projects($period));

            return view('metribay::components.overview', ['workspace' => $workspace, 'projects' => $projects, 'groups' => ProjectGroup::query()->orderBy('name')->pluck('name')->all(), 'days' => $period->days, 'period' => $period, 'selectedGroup' => $this->selectedGroup, 'sort' => $this->sort, 'direction' => $this->direction, 'now' => CarbonImmutable::now('UTC')]);
        });
    }

    /**
     * Projects needing attention come first by default; a chosen column sorts by its headline value, unknown values last.
     *
     * @param  list<array<string, mixed>>  $projects
     * @return list<array<string, mixed>>
     */
    private function sorted(array $projects): array
    {
        $byName = fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']);
        if (! array_key_exists($this->sort, DashboardData::CATEGORIES) && $this->sort !== 'name') {
            $attention = fn (array $project): int => in_array($project['status'], ['failed', 'delayed'], true) ? 0 : 1;
            usort($projects, fn (array $a, array $b): int => $attention($a) <=> $attention($b) ?: $byName($a, $b));

            return $projects;
        }
        $sign = $this->direction === 'asc' ? 1 : -1;
        usort($projects, function (array $a, array $b) use ($byName, $sign): int {
            if ($this->sort === 'name') {
                return $sign * $byName($a, $b);
            }
            $left = $a['summary'][$this->sort]['value'] ?? null;
            $right = $b['summary'][$this->sort]['value'] ?? null;
            if ($left === null || $right === null) {
                return ($left === null) <=> ($right === null) ?: $byName($a, $b);
            }

            return $sign * ($left <=> $right) ?: $byName($a, $b);
        });

        return $projects;
    }
}
