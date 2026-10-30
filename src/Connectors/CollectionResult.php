<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

final readonly class CollectionResult
{
    /**
     * @param  list<array{metric: string, date: string, value: float, is_partial: bool}>  $daily
     * @param  list<array{metric: string, start_date: string, end_date: string, value: float, is_partial: bool}>  $periods
     * @param  list<array{external_id: string, title: string, url: string, occurred_at: string, body?: string}>  $events
     * @param  array<string, list<string>>  $replaceDailyDates  Complete snapshot metric sets to replace atomically.
     */
    public function __construct(
        public array $daily = [],
        public array $periods = [],
        public array $events = [],
        public bool $authoritativeEvents = false,
        public array $replaceDailyDates = [],
    ) {}
}
