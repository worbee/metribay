<?php

namespace Metribay\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Contracts\Entitlements;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\HiddenChart;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\DataDeletion;
use Metribay\Core\Services\ProjectConfiguration;
use Metribay\Core\Services\ProjectTransfer;
use Metribay\Core\Services\ProviderCatalog;
use Metribay\Core\Services\WorkspaceContext;

class ProjectController
{
    private function authorize(Request $request): void
    {
        abort_unless(app(WorkspaceAccess::class)->allows($request->user(), app(WorkspaceContext::class)->current(), 'edit'), 403);
    }

    public function create(Request $request): View
    {
        $this->authorize($request);

        return view('metribay::project-create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $values = $this->values($request);
        $slug = substr(Str::slug($values['name']) ?: 'project', 0, 85);
        $candidate = $slug;
        $i = 2;
        while (Project::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i++;
        }
        $project = Project::create($values + ['slug' => $candidate]);

        return redirect()->route('metribay.projects.show', ['workspace' => app(WorkspaceContext::class)->current()->slug, 'project' => $project->id])
            ->with('status', __('Project created. Add a data source to start collecting metrics.'));
    }

    /** Project settings: details plus the project's data sources and the connected accounts they use. */
    public function edit(Request $request): View
    {
        $project = Project::query()->with(['sources' => fn ($query) => $query->with('connection')->orderBy('label')])->whereKey($request->route('project'))->firstOrFail();

        $hiddenCharts = HiddenChart::query()->with('source')->where('project_id', $project->id)->orderBy('id')->get();

        return view('metribay::project-settings', ['project' => $project, 'connections' => Connection::query()->orderBy('name')->get(), 'providers' => ProviderCatalog::all(), 'hiddenCharts' => $hiddenCharts]
            + $this->workspaceOptions($request));
    }

    /** Moves the project and its history to another workspace the user can edit. */
    public function move(Request $request, ProjectTransfer $transfer): RedirectResponse
    {
        $access = app(WorkspaceAccess::class);
        $from = app(WorkspaceContext::class)->current();
        abort_unless($access->allows($request->user(), $from, 'manage_access'), 403);
        $values = $request->validate(['workspace_id' => ['required', 'integer'], 'version' => ['required', 'integer', 'min:1']]);
        $to = $this->moveTargets($request)->firstWhere('id', (int) $values['workspace_id'])
            ?? throw ValidationException::withMessages(['workspace_id' => __('Choose a workspace you can edit.')]);
        $project = Project::query()->whereKey($request->route('project'))->firstOrFail();
        $moveAccounts = $access->allows($request->user(), $from, 'manage_connections') && $access->allows($request->user(), $to, 'manage_connections');
        $result = $transfer->move($from, (int) $project->id, $to, (int) $values['version'], $moveAccounts);
        $status = __('Project moved to :workspace.', ['workspace' => $to->name]);
        if ($result['detached'] > 0) {
            $status .= ' '.trans_choice('{1} Choose a connected account for :count data source in this workspace.|[2,*] Choose a connected account for :count data sources in this workspace.', $result['detached'], ['count' => $result['detached']]);
        }

        return redirect()->route('metribay.projects.edit', ['workspace' => $to->slug, 'project' => $result['project']])->with('status', $status);
    }

    /** Permanently deletes the project, its data sources, events and metric history. Connected accounts stay. */
    public function destroy(Request $request, DataDeletion $deletion): RedirectResponse
    {
        $workspace = app(WorkspaceContext::class)->current();
        abort_unless(app(WorkspaceAccess::class)->allows($request->user(), $workspace, 'manage_access'), 403);
        $project = Project::query()->whereKey($request->route('project'))->firstOrFail();
        $deletion->project($workspace, (int) $project->id);

        return redirect()->route('metribay.settings', ['workspace' => $workspace->slug, 'tab' => 'projects'])
            ->with('status', __('Project :name deleted.', ['name' => $project->name]));
    }

    /** @return Collection<int, Workspace> */
    private function moveTargets(Request $request): Collection
    {
        $access = app(WorkspaceAccess::class);

        return $access->queryFor($request->user())->whereKeyNot(app(WorkspaceContext::class)->id())->orderBy('name')->get()
            ->filter(fn (Workspace $workspace): bool => $access->allows($request->user(), $workspace, 'edit'))->values();
    }

    /**
     * Workspace choices exist only where the host exposes workspaces (Cloud); Personal and Desktop keep one internal workspace.
     *
     * @return array{moveTargets: Collection<int, Workspace>|null, workspaceCreationUrl: string|null}
     */
    private function workspaceOptions(Request $request): array
    {
        if (! Route::has('metribay.workspaces')) {
            return ['moveTargets' => null, 'workspaceCreationUrl' => null];
        }
        $creationRoute = config('metribay.workspace_creation_route');
        $canCreate = is_string($creationRoute) && Route::has($creationRoute) && app(Entitlements::class)->allows($request->user(), 'multiple_workspaces');

        return ['moveTargets' => $this->moveTargets($request), 'workspaceCreationUrl' => $canCreate ? route($creationRoute) : null];
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $values = $this->values($request);
        DB::transaction(function () use ($request, $values): void {
            $project = Project::query()->lockForUpdate()->whereKey($request->route('project'))->firstOrFail();
            $request->validate(['version' => ['required', 'integer', 'min:1']]);
            abort_if((int) $request->input('version') !== (int) $project->version, 409, __('This item changed. Reload before saving.'));
            $project->fill($values + ['archived' => $request->has('archived') ? $request->boolean('archived') : $project->archived, 'version' => $project->version + 1])->save();
        });

        return back()->with('status', __('Project updated.'));
    }

    /** @return array<string, mixed> */
    private function values(Request $request): array
    {
        $values = $request->validate(['name' => ['required', 'string', 'max:120'], 'domain' => ['nullable', 'string', 'max:253'], 'group_id' => ['nullable', 'integer', Rule::exists('project_groups', 'id')->where('workspace_id', app(WorkspaceContext::class)->id())]]);
        $values['domain'] = ProjectConfiguration::domain($values['domain'] ?? null);

        return $values;
    }
}
