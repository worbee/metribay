<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Metribay\Core\Contracts\MonthlyReportNarrative;

/** Standalone Core keeps the rules' own order. */
final class NoReportNarrative implements MonthlyReportNarrative
{
    public function rank(int $workspaceId, int $projectId, string $month): ?array
    {
        return null;
    }
}
