<?php

declare(strict_types=1);

namespace Metribay\Core\Contracts;

/**
 * Optional ranking of the rule-based summary. A host returns candidate ids from MonthlyReportRules::candidates()
 * in the order it prefers, or null to keep the rules' own order. Hosts never supply text: every language and
 * every number stay the rules' own, and collection problems are always kept.
 */
interface MonthlyReportNarrative
{
    /**
     * @return array{sentences: list<string>, actions: list<string>}|null
     */
    public function rank(int $workspaceId, int $projectId, string $month): ?array;
}
