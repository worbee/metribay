<?php

namespace Metribay\Core\Metrics;

final readonly class MetricDefinition
{
    /**
     * @param  array{0: string, 1: string, 2: float}|null  $ratio  For aggregation "ratio": numerator and denominator metrics and a
     *                                                             scale. Ratios are never stored; a day or period is computed
     *                                                             from the sums of their parts, never by averaging daily ratios.
     */
    public function __construct(public string $key, public string $label, public string $unit, public string $aggregation, public bool $snapshot = false, public ?array $ratio = null) {}
}
