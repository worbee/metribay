<?php

namespace Metribay\Core\Services;

use Illuminate\Support\Facades\DB;
use Metribay\Core\Models\Workspace;

/**
 * Writes a workspace's collected metric history as CSV: one row per daily value and one per period value.
 * Daily rows have the same start and end date. Values are written as stored; missing days have no row.
 */
class HistoryExport
{
    public const COLUMNS = ['project', 'domain', 'data_source', 'provider', 'metric', 'start_date', 'end_date', 'value', 'partial'];

    /** @param  resource  $stream */
    public function write(Workspace $workspace, $stream): void
    {
        fputcsv($stream, self::COLUMNS, escape: '');
        $sources = DB::table('sources')->join('projects', function ($join): void {
            $join->on('projects.id', '=', 'sources.project_id')->on('projects.workspace_id', '=', 'sources.workspace_id');
        })->where('sources.workspace_id', $workspace->id)
            ->orderBy('projects.name')->orderBy('projects.id')->orderBy('sources.label')->orderBy('sources.id')
            ->get(['sources.id', 'sources.kind', 'sources.label', 'projects.name as project', 'projects.domain']);

        foreach ($sources as $source) {
            $context = [$this->text($source->project), $this->text($source->domain), $this->text($source->label), $source->kind];
            foreach (DB::table('daily_metrics')->where('workspace_id', $workspace->id)->where('source_id', $source->id)->orderBy('metric')->orderBy('date')->lazy(2000) as $row) {
                fputcsv($stream, [...$context, $row->metric, substr((string) $row->date, 0, 10), substr((string) $row->date, 0, 10), $this->number($row->value), $row->is_partial ? 'yes' : 'no'], escape: '');
            }
            foreach (DB::table('period_metrics')->where('workspace_id', $workspace->id)->where('source_id', $source->id)->orderBy('metric')->orderBy('start_date')->orderBy('end_date')->lazy(2000) as $row) {
                fputcsv($stream, [...$context, $row->metric, substr((string) $row->start_date, 0, 10), substr((string) $row->end_date, 0, 10), $this->number($row->value), $row->is_partial ? 'yes' : 'no'], escape: '');
            }
        }
    }

    /** Text cells that a spreadsheet would read as a formula get a leading apostrophe. */
    private function text(?string $value): string
    {
        $value ??= '';

        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }

    private function number(mixed $value): string
    {
        $float = (float) $value;

        return $float == floor($float) && abs($float) < 1e15 ? (string) (int) $float : rtrim(rtrim(sprintf('%.6F', $float), '0'), '.');
    }
}
