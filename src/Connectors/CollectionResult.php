<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

final readonly class CollectionResult
{
    /**
     * $queries are the top search queries per reporting window: null leaves stored queries alone, a list (even an empty one) replaces them.
     * $queryMonths are complete calendar months of final top queries to archive; a month that is already archived is kept as it is.
     *
     * @param  list<array{metric: string, date: string, value: float, is_partial: bool, context?: array<string,string|int>, window_start?: string, window_end?: string}>  $daily
     * @param  list<array{metric: string, start_date: string, end_date: string, value: float, is_partial: bool}>  $periods
     * @param  list<array{origin: string, external_id: string, title: string, url: string, occurred_at: string|null, body?: string}>  $events
     * @param  array<string, string|null>  $authoritativeEvents  Event origins read completely: origin => ISO 8601 start of the
     *                                                           window that was read (null for all time). Stored events of that
     *                                                           origin in the window that are missing from $events are removed.
     * @param  array<string, list<string>>  $replaceDailyDates  Complete snapshot metric sets to replace atomically.
     * @param  list<array{days: int, previous: bool, start_date: string, end_date: string, query: string, clicks: float, impressions: float, position: float|null, is_partial: bool}>|null  $queries
     * @param  list<array{month: string, start_date: string, end_date: string, weeks: int|null, rows: list<array{query: string, clicks: float, impressions: float, position: float|null}>}>  $queryMonths
     * @param  list<array{url: string, last_modified_at: string|null}>|null  $sitemapUrls  A complete sitemap catalog, independent of optional events. Null leaves tracking unchanged.
     * @param  list<array{date: string, visits: array<string, float>, is_partial: bool}>|null  $channelDays  Visits per channel for each date the provider answered for; those dates are replaced. Null leaves stored channels alone.
     * @param  list<array{dimension: string, days: int, previous: bool, start_date: string, end_date: string, item: string, visits: float|null, visitors: float|null, conversions: float|null, is_partial: bool}>  $breakdowns
     * @param  list<string>  $breakdownDimensions  Dimensions read completely: their stored rows are replaced by $breakdowns. Other dimensions stay as they are.
     * @param  array<string, string>  $breakdownErrors  Safe messages of dimensions or channel days that could not be read, for the log.
     */
    public function __construct(
        public array $daily = [],
        public array $periods = [],
        public array $events = [],
        public array $authoritativeEvents = [],
        public array $replaceDailyDates = [],
        public ?array $queries = null,
        public array $queryMonths = [],
        public ?array $sitemapUrls = null,
        public ?array $channelDays = null,
        public array $breakdowns = [],
        public array $breakdownDimensions = [],
        public array $breakdownErrors = [],
    ) {}
}
