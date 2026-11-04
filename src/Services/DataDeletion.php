<?php

namespace Metribay\Core\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Workspace;

/**
 * Permanently deletes projects with everything that belongs to them: data sources, metric history,
 * top queries and their monthly archive, sync runs, events and hidden charts. Rows are deleted
 * explicitly so the result does not depend on the database enforcing cascading foreign keys.
 */
class DataDeletion
{
    /** Tables whose rows belong to exactly one data source. */
    private const SOURCE_TABLES = ['daily_metrics', 'period_metrics', 'search_queries', 'sync_runs'];

    public function project(Workspace $workspace, int $projectId): void
    {
        DB::transaction(function () use ($workspace, $projectId): void {
            DB::table('projects')->where('workspace_id', $workspace->id)->where('id', $projectId)->lockForUpdate()->first()
                ?? throw (new ModelNotFoundException)->setModel(Project::class, [$projectId]);
            $this->deleteProjects($workspace, collect([$projectId]));
        });
    }

    /**
     * Deletes every project, data source, metric, event, connected account (with its saved credentials) and
     * group in the workspace. The workspace itself and its members stay, so the owner can start again.
     *
     * @return array{projects: int, connections: int}
     */
    public function workspace(Workspace $workspace): array
    {
        return DB::transaction(function () use ($workspace): array {
            DB::table('workspaces')->where('id', $workspace->id)->lockForUpdate()->first();
            $projectIds = DB::table('projects')->where('workspace_id', $workspace->id)->pluck('id')->map(fn ($id): int => (int) $id);
            $this->deleteProjects($workspace, $projectIds);
            // Events and hidden charts always belong to a project, but clear any left over from older data.
            DB::table('project_events')->where('workspace_id', $workspace->id)->delete();
            DB::table('hidden_charts')->where('workspace_id', $workspace->id)->delete();
            $connections = DB::table('connections')->where('workspace_id', $workspace->id)->delete();
            DB::table('project_groups')->where('workspace_id', $workspace->id)->delete();

            return ['projects' => $projectIds->count(), 'connections' => $connections];
        });
    }

    /** @param  Collection<int, int>  $projectIds */
    private function deleteProjects(Workspace $workspace, Collection $projectIds): void
    {
        foreach ($projectIds->chunk(200) as $projects) {
            $sourceIds = DB::table('sources')->where('workspace_id', $workspace->id)->whereIn('project_id', $projects)->pluck('id');
            foreach ($sourceIds->chunk(200) as $sources) {
                $months = DB::table('search_query_months')->where('workspace_id', $workspace->id)->whereIn('source_id', $sources)->pluck('id');
                foreach ($months->chunk(500) as $chunk) {
                    DB::table('search_query_month_rows')->whereIn('search_query_month_id', $chunk)->delete();
                }
                DB::table('search_query_months')->where('workspace_id', $workspace->id)->whereIn('source_id', $sources)->delete();
                foreach (self::SOURCE_TABLES as $table) {
                    DB::table($table)->where('workspace_id', $workspace->id)->whereIn('source_id', $sources)->delete();
                }
            }
            DB::table('project_events')->where('workspace_id', $workspace->id)->whereIn('project_id', $projects)->delete();
            DB::table('hidden_charts')->where('workspace_id', $workspace->id)->whereIn('project_id', $projects)->delete();
            DB::table('sources')->where('workspace_id', $workspace->id)->whereIn('project_id', $projects)->delete();
            DB::table('projects')->where('workspace_id', $workspace->id)->whereIn('id', $projects)->delete();
        }
    }
}
