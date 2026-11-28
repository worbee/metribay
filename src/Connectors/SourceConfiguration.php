<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

final readonly class SourceConfiguration
{
    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $archivedQueryMonths  Months (Y-m) whose top queries are already archived; connectors skip them.
     * @param  bool  $breakdownLists  Whether this collection reads the audience top lists, which refresh about once a day (Collector).
     * @param  list<string>  $periodMonths  Closed calendar months (Y-m) whose provider totals (unique visitors, active users) are missing or provisional.
     * @param  list<string>  $breakdownMonths  Closed calendar months (Y-m) whose top lists are not archived yet.
     */
    public function __construct(
        public string $kind,
        public array $config,
        public string $reportingTimezone = 'UTC',
        public ?CarbonImmutable $from = null,
        public array $archivedQueryMonths = [],
        public bool $breakdownLists = true,
        public array $periodMonths = [],
        public array $breakdownMonths = [],
    ) {}
}
