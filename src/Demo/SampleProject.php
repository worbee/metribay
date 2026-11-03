<?php

declare(strict_types=1);

namespace Metribay\Core\Demo;

/** A fictional project of the sample portfolio. Provider events travel with their source's collections. */
final readonly class SampleProject
{
    /**
     * @param  list<SampleSource>  $sources
     * @param  list<array{date: string, title: string, color: string, body?: string, url?: string, recurrence?: string, recurrence_until?: string}>  $events  Custom events people entered.
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $domain,
        public string $audience,
        public array $sources,
        public array $events,
    ) {}
}
