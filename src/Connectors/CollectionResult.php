<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

final readonly class CollectionResult
{
    /**
     * @param  list<array{metric: string, date: string, value: float, is_partial: bool, context?: array<string,string|int>, window_start?: string, window_end?: string}>  $daily
     * @param  list<array{metric: string, start_date: string, end_date: string, value: float, is_partial: bool}>  $periods
     * @param  list<array{origin: string, external_id: string, title: string, url: string, occurred_at: string, body?: string}>  $events
     * @param  array<string, string|null>  $authoritativeEvents  Event origins read completely: origin => ISO 8601 start of the
     *                                                           window that was read (null for all time). Stored events of that
     *                                                           origin in the window that are missing from $events are removed.
     * @param  array<string, list<string>>  $replaceDailyDates  Complete snapshot metric sets to replace atomically.
     */
    public function __construct(
        public array $daily = [],
        public array $periods = [],
        public array $events = [],
        public array $authoritativeEvents = [],
        public array $replaceDailyDates = [],
    ) {}
}
