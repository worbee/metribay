<?php

namespace Metribay\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Metrics\MetricRegistry;
use Metribay\Core\Metrics\ProjectLayout;
use Metribay\Core\Models\HiddenChart;
use Metribay\Core\Models\Project;
use Metribay\Core\Services\SearchQueries;
use Metribay\Core\Services\WorkspaceContext;

class HiddenChartController
{
    private function project(Request $request): Project
    {
        abort_unless(app(WorkspaceAccess::class)->allows($request->user(), app(WorkspaceContext::class)->current(), 'edit'), 403);

        return Project::query()->whereKey($request->route('project'))->firstOrFail();
    }

    public function store(Request $request): RedirectResponse
    {
        $project = $this->project($request);
        $values = $request->validate([
            'source_id' => ['required', 'integer', Rule::exists('sources', 'id')->where('workspace_id', app(WorkspaceContext::class)->id())->where('project_id', $project->id)],
            'metric_key' => ['required', 'string', Rule::in([...array_keys(MetricRegistry::all()), SearchQueries::CHART_KEY])],
        ]);
        $chart = HiddenChart::query()->createOrFirst(['project_id' => $project->id, 'source_id' => (int) $values['source_id'], 'metric_key' => $values['metric_key']]);

        // Return to the data source the chart belonged to, where the undo notice is shown.
        return redirect()->to(strtok(url()->previous(), '#').'#source-'.$chart->source_id)->with('chart_hidden', $chart->id);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $project = $this->project($request);
        $chart = HiddenChart::query()->where('project_id', $project->id)->whereKey($request->route('chart'))->firstOrFail();
        $chart->delete();
        $label = ProjectLayout::chartLabel($chart->metric_key);

        if ($request->input('return') === 'project') {
            return redirect()->to(strtok(url()->previous(), '#').'#chart-'.$chart->source_id.'-'.$chart->metric_key.'-label')->with('status', __(':chart chart is shown again.', ['chart' => $label]));
        }

        return back()->with('status', __(':chart chart is shown again.', ['chart' => $label]));
    }
}
