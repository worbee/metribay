<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

final readonly class SourceConfiguration
{
    /** @param array<string, mixed> $config */
    public function __construct(
        public string $kind,
        public array $config,
        public string $reportingTimezone = 'UTC',
        public ?CarbonImmutable $from = null,
    ) {}
}
