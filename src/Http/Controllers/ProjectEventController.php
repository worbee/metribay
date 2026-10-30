<?php

namespace Metribay\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Services\WorkspaceContext;

class ProjectEventController
{
    /** @return array<string, mixed> */
    private function values(Request $request, Project $project): array
    {
        $values = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'title' => ['required', 'string', 'max:200'], 'body' => ['nullable', 'string', 'max:4000'], 'url' => ['nullable', 'url:http,https', 'max:2000'], 'source_id' => ['nullable', 'integer', Rule::exists('sources', 'id')->where('workspace_id', app(WorkspaceContext::class)->id())->where('project_id', $project->id)],
            'color' => ['nullable', Rule::in(ProjectEvent::COLORS)], 'recurrence' => ['nullable', Rule::in(ProjectEvent::RECURRENCES)], 'recurrence_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date']]);
        if (! empty($values['url'])) {
            $url = parse_url($values['url']);
            if (isset($url['user']) || isset($url['pass']) || preg_match('/[\x00-\x20]/', $values['url'])) {
                throw ValidationException::withMessages(['url' => __('Use a public HTTP or HTTPS URL without credentials.')]);
            }
        }
        $values['color'] ??= ProjectEvent::COLORS[0];
        $values['recurrence'] ??= null;
        $values['recurrence_until'] = $values['recurrence'] ? ($values['recurrence_until'] ?? null) : null;

        return $values;
    }

    private function project(Request $request): Project
    {
        abort_unless(app(WorkspaceAccess::class)->allows($request->user(), app(WorkspaceContext::class)->current(), 'edit'), 403);

        return Project::query()->whereKey($request->route('project'))->firstOrFail();
    }

    private function custom(Request $request, Project $project): ProjectEvent
    {
        $event = ProjectEvent::query()->where('project_id', $project->id)->whereKey($request->route('event'))->firstOrFail();
        // Automatic events are reconciled by their collector and cannot be edited by hand.
        abort_unless($event->isCustom(), 403);

        return $event;
    }

    public function store(Request $request): RedirectResponse
    {
        $project = $this->project($request);
        ProjectEvent::create($this->values($request, $project) + ['project_id' => $project->id, 'origin' => 'manual']);

        return back()->with('status', __('Event added.'));
    }

    public function update(Request $request): RedirectResponse
    {
        $project = $this->project($request);
        $event = $this->custom($request, $project);
        $event->update($this->values($request, $project));

        return back()->with('status', __('Event updated.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $project = $this->project($request);
        $this->custom($request, $project)->delete();

        return back()->with('status', __('Event deleted.'));
    }
}
