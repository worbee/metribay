<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

final readonly class SourceConfiguration
{
    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $archivedQueryMonths  Months (Y-m) whose top queries are already archived; connectors skip them.
     */
    public function __construct(
        public string $kind,
        public array $config,
        public string $reportingTimezone = 'UTC',
        public ?CarbonImmutable $from = null,
        public array $archivedQueryMonths = [],
    ) {}
}
