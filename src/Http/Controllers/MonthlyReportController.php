<?php

declare(strict_types=1);

namespace Metribay\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Metribay\Core\Contracts\MonthlyReportNarrative;
use Metribay\Core\Models\MonthlyReport;
use Metribay\Core\Models\Project;
use Metribay\Core\Services\MonthlyReportRules;
use Metribay\Core\Services\WorkspaceContext;

class MonthlyReportController
{
    public function show(Request $request, MonthlyReportRules $rules): View|RedirectResponse
    {
        $project = Project::query()->whereKey($request->route('project'))->firstOrFail();
        $workspace = app(WorkspaceContext::class)->current();
        $chosen = $request->query('month');
        // The month picker submits ?month= so it works without JavaScript.
        if (is_string($chosen) && preg_match('/^\d{4}-\d{2}$/D', $chosen) === 1 && $request->route('month') === null) {
            return redirect()->route('metribay.projects.report', ['workspace' => $workspace->slug, 'project' => $project->id, 'month' => $chosen]);
        }
        $month = $request->route('month');
        $available = MonthlyReport::query()->where('project_id', $project->id)->orderByDesc('month')->get();
        if (is_string($month) && $month !== '') {
            abort_unless(preg_match('/^\d{4}-\d{2}$/D', $month) === 1, 404);
            $report = $available->first(fn (MonthlyReport $row): bool => $row->month->format('Y-m') === $month);
            abort_if($report === null, 404);
        } else {
            $report = $available->first();
        }
        $reading = null;
        $newer = null;
        $older = null;
        if ($report instanceof MonthlyReport) {
            $key = $report->month->format('Y-m');
            $ranking = app(MonthlyReportNarrative::class)->rank((int) $report->workspace_id, (int) $report->project_id, $key);
            $reading = $rules->read($report->payload, $ranking);
            $index = $available->search(fn (MonthlyReport $row): bool => $row->is($report));
            $newer = $index > 0 ? $available[$index - 1] : null;
            $older = $available[$index + 1] ?? null;
        }

        return view('metribay::monthly-report', [
            'workspace' => $workspace,
            'project' => $project,
            'report' => $report,
            'reading' => $reading,
            'rules' => $rules,
            'months' => $available,
            'newer' => $newer,
            'older' => $older,
        ]);
    }
}
