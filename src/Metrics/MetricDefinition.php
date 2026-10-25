<?php

namespace Metribay\Core\Metrics;

final readonly class MetricDefinition
{
    public function __construct(public string $key, public string $label, public string $unit, public string $aggregation, public bool $snapshot = false) {}
}
