<?php

namespace Metribay\Core\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Workspace;

/**
 * Moves a project, its data sources, events, hidden charts and metric history to another workspace.
 * Composite workspace keys cannot change in place, so the project and its sources are recreated in the
 * target workspace and their history is re-pointed; the project and source IDs therefore change.
 */
class ProjectTransfer
{
    /** Tables whose rows belong to exactly one data source. */
    private const SOURCE_TABLES = ['daily_metrics', 'period_metrics', 'sitemap_urls', 'search_queries', 'search_query_months', 'sync_runs'];

    /**
     * Connected accounts used only by this project move along when $moveAccounts is true. Accounts shared
     * with other projects stay; the moved data sources keep their settings and history without an account.
     *
     * @return array{project: int, accounts: int, detached: int}
     */
    public function move(Workspace $from, int $projectId, Workspace $to, int $version, bool $moveAccounts): array
    {
        return DB::transaction(function () use ($from, $projectId, $to, $version, $moveAccounts): array {
            DB::table('workspaces')->whereIn('id', [$from->id, $to->id])->orderBy('id')->lockForUpdate()->get();
            $project = DB::table('projects')->where('workspace_id', $from->id)->where('id', $projectId)->lockForUpdate()->first()
                ?? throw (new ModelNotFoundException)->setModel(Project::class, [$projectId]);
            abort_if($version !== (int) $project->version, 409, __('This item changed. Reload before saving.'));
            $sources = DB::table('sources')->where('workspace_id', $from->id)->where('project_id', $projectId)->orderBy('id')->lockForUpdate()->get();
            $connectionIds = $sources->pluck('connection_id')->filter()->unique()->values();
            $shared = DB::table('sources')->where('workspace_id', $from->id)->whereIn('connection_id', $connectionIds)->where('project_id', '!=', $projectId)->pluck('connection_id');
            $movable = $moveAccounts ? $connectionIds->diff($shared)->values() : collect();

            $newProjectId = DB::table('projects')->insertGetId(['workspace_id' => $to->id, 'group_id' => null, 'name' => $project->name,
                'slug' => $this->available('projects', $to, 'slug', $project->slug, 100, '-'), 'domain' => $project->domain,
                'archived' => $project->archived, 'version' => 1, 'created_at' => $project->created_at, 'updated_at' => now()]);
            // Release the old workspace's account references before any account changes workspace.
            DB::table('sources')->whereIn('id', $sources->pluck('id'))->update(['connection_id' => null]);
            foreach ($movable as $connectionId) {
                $this->moveConnection($from, (int) $connectionId, $to);
            }

            foreach ($sources as $source) {
                $row = array_diff_key((array) $source, ['id' => true]);
                $newSourceId = DB::table('sources')->insertGetId(['workspace_id' => $to->id, 'project_id' => $newProjectId,
                    'connection_id' => $movable->contains($source->connection_id) ? $source->connection_id : null, 'version' => 1] + $row);
                foreach (self::SOURCE_TABLES as $table) {
                    DB::table($table)->where('workspace_id', $from->id)->where('source_id', $source->id)->update(['workspace_id' => $to->id, 'source_id' => $newSourceId]);
                }
                foreach (['project_events', 'hidden_charts'] as $table) {
                    DB::table($table)->where('workspace_id', $from->id)->where('source_id', $source->id)->update(['workspace_id' => $to->id, 'project_id' => $newProjectId, 'source_id' => $newSourceId]);
                }
            }
            DB::table('project_events')->where('workspace_id', $from->id)->where('project_id', $projectId)->whereNull('source_id')->update(['workspace_id' => $to->id, 'project_id' => $newProjectId]);
            DB::table('sources')->whereIn('id', $sources->pluck('id'))->delete();
            DB::table('projects')->where('id', $projectId)->delete();

            return ['project' => $newProjectId, 'accounts' => $movable->count(),
                'detached' => $sources->filter(fn ($source) => $source->connection_id && ! $movable->contains($source->connection_id))->count()];
        });
    }

    /** Re-binds the account's encrypted credentials to the target workspace; they are never duplicated. */
    private function moveConnection(Workspace $from, int $connectionId, Workspace $to): void
    {
        $connection = Connection::query()->withoutGlobalScope('workspace')->where('workspace_id', $from->id)->lockForUpdate()->findOrFail($connectionId);
        $values = $connection->reveal();
        $connection->workspace_id = $to->id;
        $connection->storeSecrets($values);
        DB::table('connections')->where('id', $connectionId)->update(['workspace_id' => $to->id, 'encrypted_payload' => $connection->getAttributes()['encrypted_payload'],
            'name' => $this->available('connections', $to, 'name', $connection->name, 120, ' '), 'version' => $connection->version + 1, 'updated_at' => now()]);
    }

    /** The value itself, or the value with the first free numeric suffix in the target workspace. */
    private function available(string $table, Workspace $to, string $column, string $value, int $length, string $separator): string
    {
        $candidate = $value;
        $i = 2;
        while (DB::table($table)->where('workspace_id', $to->id)->where($column, $candidate)->exists()) {
            $suffix = $separator.$i++;
            $candidate = Str::substr($value, 0, $length - Str::length($suffix)).$suffix;
        }

        return $candidate;
    }
}
