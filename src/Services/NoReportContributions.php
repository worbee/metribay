<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Metribay\Core\Contracts\MonthlyReportContributions;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Workspace;

/** Standalone Core has no host-supplied report items. */
final class NoReportContributions implements MonthlyReportContributions
{
    public function capture(Workspace $workspace, Project $project, string $month): array
    {
        return [];
    }
}
