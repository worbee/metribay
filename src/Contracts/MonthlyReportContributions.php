<?php

declare(strict_types=1);

namespace Metribay\Core\Contracts;

use Metribay\Core\Models\Project;
use Metribay\Core\Models\Workspace;

/**
 * Host notes frozen into a monthly report: structured translation keys, never free text that
 * would be stuck in one language. Core stores them with the snapshot and renders them later.
 */
interface MonthlyReportContributions
{
    /**
     * @return list<array{key: string, title_key: string, detail_key: string, evidence_key: string, params: array<string, string|int|float>, weight: int}>
     */
    public function capture(Workspace $workspace, Project $project, string $month): array;
}
