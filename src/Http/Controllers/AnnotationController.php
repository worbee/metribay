<?php

namespace Metribay\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Models\Annotation;
use Metribay\Core\Models\Project;
use Metribay\Core\Services\WorkspaceContext;

class AnnotationController
{
    /** @return array<string, mixed> */
    private function values(Request $request, Project $project): array
    {
        $values = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'title' => ['required', 'string', 'max:200'], 'body' => ['nullable', 'string', 'max:4000'], 'url' => ['nullable', 'url:http,https', 'max:2000'], 'source_id' => ['nullable', 'integer', Rule::exists('sources', 'id')->where('workspace_id', app(WorkspaceContext::class)->id())->where('project_id', $project->id)]]);
        if (! empty($values['url'])) {
            $url = parse_url($values['url']);
            if (isset($url['user']) || isset($url['pass']) || preg_match('/[\x00-\x20]/', $values['url'])) {
                throw ValidationException::withMessages(['url' => 'Use a public HTTP or HTTPS URL without credentials.']);
            }
        }

        return $values;
    }

    private function project(Request $request): Project
    {
        abort_unless(app(WorkspaceAccess::class)->allows($request->user(), app(WorkspaceContext::class)->current(), 'edit'), 403);

        return Project::query()->whereKey($request->route('project'))->firstOrFail();
    }

    public function store(Request $request): RedirectResponse
    {
        $project = $this->project($request);
        Annotation::create($this->values($request, $project) + ['project_id' => $project->id, 'origin' => 'manual']);

        return back()->with('status', 'Annotation added.');
    }

    public function update(Request $request): RedirectResponse
    {
        $project = $this->project($request);
        $annotation = Annotation::query()->where('project_id', $project->id)->whereKey($request->route('annotation'))->firstOrFail();
        abort_unless($annotation->origin === 'manual', 403);
        $annotation->update($this->values($request, $project));

        return back()->with('status', 'Annotation updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $project = $this->project($request);
        $annotation = Annotation::query()->where('project_id', $project->id)->whereKey($request->route('annotation'))->firstOrFail();
        abort_unless($annotation->origin === 'manual', 403);
        $annotation->delete();

        return back()->with('status', 'Annotation deleted.');
    }
}
