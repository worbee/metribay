<?php

declare(strict_types=1);

namespace Metribay\Core\Demo;

use Carbon\CarbonImmutable;
use Metribay\Core\Connectors\CollectionResult;

/** One sample data source and the collections that would have filled it, in the order they happened. */
final readonly class SampleSource
{
    /**
     * @param  array<string, mixed>  $config
     * @param  list<array{result: CollectionResult, at: CarbonImmutable}>  $collections
     */
    public function __construct(
        public string $kind,
        public string $label,
        public array $config,
        public string $timezone,
        public array $collections,
        public CarbonImmutable $lastSuccess,
    ) {}
}
