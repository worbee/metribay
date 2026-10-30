<?php

namespace Metribay\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Services\ProjectConfiguration;
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

        return view('metribay::project-settings', ['project' => $project, 'connections' => Connection::query()->orderBy('name')->get(), 'providers' => ProviderCatalog::all()]);
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
