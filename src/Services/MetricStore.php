<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Metribay\Core\Connectors\Breakdowns;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Metrics\MetricRegistry;
use Metribay\Core\Models\BreakdownMonth;
use Metribay\Core\Models\BreakdownMonthRow;
use Metribay\Core\Models\BreakdownRow;
use Metribay\Core\Models\ChannelDay;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\PeriodMetric;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\SearchQuery;
use Metribay\Core\Models\SearchQueryMonth;
use Metribay\Core\Models\SearchQueryMonthRow;
use Metribay\Core\Models\Source;

class MetricStore
{
    private function date(string $date): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            throw new InvalidArgumentException('Invalid metric date.');
        }
    }

    private function value(string $metric, mixed $value): void
    {
        $definition = MetricRegistry::get($metric);
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('Metric values must be numeric.');
        }
        if (! is_finite((float) $value) || $value < 0 || (in_array($definition->unit, ['percent', 'score'], true) && $value > 100) || ($definition->unit === 'status' && ! in_array($value, [0, 1, 2, 8, 9, 0.0, 1.0, 2.0, 8.0, 9.0], true))) {
            throw new InvalidArgumentException('Metric value is outside its valid range.');
        }
    }

    /** @return array<string,string|int> */
    private function context(mixed $context): array
    {
        if (! is_array($context)) {
            throw new InvalidArgumentException('Invalid metric context.');
        }
        foreach ($context as $key => $item) {
            if (! is_string($key) || (! is_string($item) && ! is_int($item)) || strlen((string) $item) > 1000) {
                throw new InvalidArgumentException('Invalid metric context.');
            }
        }

        return $context;
    }

    /** @param array<string, mixed> $row */
    private function query(Source $source, array $row): void
    {
        if (! in_array($source->kind, ProviderCatalog::QUERY_PROVIDERS, true) || ! in_array($row['days'] ?? null, [7, 30, 90], true) || ! is_bool($row['previous'] ?? null) || ! is_bool($row['is_partial'] ?? null)) {
            throw new InvalidArgumentException('Invalid search query window.');
        }
        $this->date($row['start_date'] ?? '');
        $this->date($row['end_date'] ?? '');
        if ($row['start_date'] > $row['end_date'] || ! is_string($row['query'] ?? null) || trim($row['query']) === '' || mb_strlen($row['query']) > 1000) {
            throw new InvalidArgumentException('Invalid search query.');
        }
        foreach (['clicks', 'impressions'] as $field) {
            if ((! is_int($row[$field] ?? null) && ! is_float($row[$field] ?? null)) || ! is_finite((float) $row[$field]) || $row[$field] < 0) {
                throw new InvalidArgumentException('Invalid search query count.');
            }
        }
        if ($row['position'] !== null && ((! is_int($row['position']) && ! is_float($row['position'])) || ! is_finite((float) $row['position']) || $row['position'] <= 0)) {
            throw new InvalidArgumentException('Invalid search query position.');
        }
    }

    /** @param list<array<string, mixed>> $months */
    private function breakdownMonths(Source $source, array $months): void
    {
        if ($months !== [] && ! ProviderCatalog::hasBreakdowns($source->kind, $source->config ?? [])) {
            throw new InvalidArgumentException('This provider does not report audience breakdowns.');
        }
        $seen = [];
        foreach ($months as $month) {
            foreach (['month', 'start_date', 'end_date'] as $field) {
                $this->date(is_string($month[$field] ?? null) ? $month[$field] : '');
            }
            $dimension = $month['dimension'] ?? null;
            if (! is_string($dimension) || ! in_array($dimension, Breakdowns::DIMENSIONS, true) || substr($month['month'], 8) !== '01'
                || isset($seen[$dimension.$month['month']]) || $month['start_date'] > $month['end_date'] || substr($month['start_date'], 0, 7) !== substr($month['month'], 0, 7)
                || substr($month['end_date'], 0, 7) !== substr($month['month'], 0, 7) || ! is_bool($month['is_partial'] ?? null) || ! is_array($month['rows'] ?? null) || ! array_is_list($month['rows'])) {
                throw new InvalidArgumentException('Invalid breakdown month.');
            }
            $seen[$dimension.$month['month']] = true;
            $items = [];
            foreach ($month['rows'] as $row) {
                if (! is_array($row) || ! is_string($row['item'] ?? null) || $row['item'] === '' || isset($items[$row['item']])) {
                    throw new InvalidArgumentException('Invalid breakdown month.');
                }
                foreach (['visits', 'visitors', 'conversions'] as $field) {
                    $value = $row[$field] ?? null;
                    if ($value !== null && ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0)) {
                        throw new InvalidArgumentException('Invalid breakdown month.');
                    }
                }
                $items[$row['item']] = true;
            }
        }
    }

    /** @param list<array<string, mixed>> $months */
    private function queryMonths(Source $source, array $months): void
    {
        if ($months !== [] && ! in_array($source->kind, ProviderCatalog::QUERY_PROVIDERS, true)) {
            throw new InvalidArgumentException('Invalid search query month.');
        }
        $seen = [];
        foreach ($months as $month) {
            foreach (['month', 'start_date', 'end_date'] as $field) {
                $this->date(is_string($month[$field] ?? null) ? $month[$field] : '');
            }
            $weeks = $month['weeks'] ?? null;
            if (substr($month['month'], 8) !== '01' || isset($seen[$month['month']]) || $month['start_date'] > $month['end_date'] || substr($month['start_date'], 0, 7) !== substr($month['month'], 0, 7)
                || substr($month['end_date'], 0, 7) !== substr($month['month'], 0, 7) || ($weeks !== null && (! is_int($weeks) || $weeks < 1 || $weeks > 6)) || ! is_array($month['rows'] ?? null) || ! array_is_list($month['rows'])) {
                throw new InvalidArgumentException('Invalid search query month.');
            }
            $seen[$month['month']] = true;
            $queries = [];
            foreach ($month['rows'] as $row) {
                $this->query($source, ['days' => 30, 'previous' => false, 'is_partial' => false, 'start_date' => $month['start_date'], 'end_date' => $month['end_date']] + (is_array($row) ? $row : []));
                if (isset($queries[$row['query']])) {
                    throw new InvalidArgumentException('Duplicate search query.');
                }
                $queries[$row['query']] = true;
            }
        }
    }

    /** Validates channel days and breakdown rows before anything is written. */
    private function breakdowns(Source $source, CollectionResult $result): void
    {
        $supported = ProviderCatalog::hasBreakdowns($source->kind, $source->config ?? []);
        if (($result->channelDays !== null || $result->breakdownDimensions !== [] || $result->breakdowns !== []) && ! $supported) {
            throw new InvalidArgumentException('This provider does not report audience breakdowns.');
        }
        $dates = [];
        foreach ($result->channelDays ?? [] as $day) {
            $date = $this->channelDay($day);
            if (isset($dates[$date])) {
                throw new InvalidArgumentException('Invalid channel visits.');
            }
            $dates[$date] = true;
        }
        $dimensions = $result->breakdownDimensions;
        if (count(array_unique($dimensions)) !== count($dimensions) || array_diff($dimensions, Breakdowns::DIMENSIONS) !== []) {
            throw new InvalidArgumentException('Invalid breakdown dimension.');
        }
        $seen = [];
        foreach ($result->breakdowns as $row) {
            $key = $this->breakdownRow($row, $dimensions);
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Duplicate breakdown item.');
            }
            $seen[$key] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $day
     * @return string The date.
     */
    private function channelDay(array $day): string
    {
        $date = $day['date'] ?? null;
        $this->date(is_string($date) ? $date : '');
        if (! is_bool($day['is_partial'] ?? null) || ! is_array($day['visits'] ?? null)) {
            throw new InvalidArgumentException('Invalid channel visits.');
        }
        foreach ($day['visits'] as $channel => $visits) {
            if (! in_array($channel, Breakdowns::CHANNELS, true) || ! is_float($visits) || ! is_finite($visits) || $visits < 0) {
                throw new InvalidArgumentException('Invalid channel visits.');
            }
        }

        return (string) $date;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $dimensions  Dimensions read in this collection.
     * @return string The row's identity within the collection.
     */
    private function breakdownRow(array $row, array $dimensions): string
    {
        $dimension = $row['dimension'] ?? null;
        if (! in_array($dimension, $dimensions, true) || ! in_array($row['days'] ?? null, Breakdowns::WINDOWS, true) || ! is_bool($row['previous'] ?? null) || ! is_bool($row['is_partial'] ?? null)) {
            throw new InvalidArgumentException('Invalid breakdown window.');
        }
        $start = $row['start_date'] ?? null;
        $end = $row['end_date'] ?? null;
        $this->date(is_string($start) ? $start : '');
        $this->date(is_string($end) ? $end : '');
        $item = $row['item'] ?? null;
        if ($start > $end || ! is_string($item) || trim($item) === '' || mb_strlen($item) > 1000
            || ($dimension === 'channel' && ! in_array($item, Breakdowns::CHANNELS, true))
            || ($dimension === 'device' && ! in_array($item, Breakdowns::DEVICES, true))
            || ($dimension === 'country' && $item !== 'unknown' && ! preg_match('/^[A-Z]{2}$/D', $item))) {
            throw new InvalidArgumentException('Invalid breakdown item.');
        }
        $known = 0;
        foreach (['visits', 'visitors', 'conversions'] as $measure) {
            $value = $row[$measure] ?? null;
            if ($value !== null && (! is_float($value) || ! is_finite($value) || $value < 0)) {
                throw new InvalidArgumentException('Invalid breakdown count.');
            }
            $known += $value === null ? 0 : 1;
        }
        if ($known === 0) {
            throw new InvalidArgumentException('Invalid breakdown count.');
        }

        return $dimension.'|'.$row['days'].'|'.($row['previous'] ? 1 : 0).'|'.$item;
    }

    public function persist(Source $source, CollectionResult $result, CarbonImmutable $now): int
    {
        $allowed = ProviderCatalog::storedMetricKeys($source->kind, $source->config ?? []);
        foreach ($result->daily as $row) {
            if (! in_array($row['metric'], $allowed, true)) {
                throw new InvalidArgumentException('Metric does not belong to this provider.');
            }$this->date($row['date']);
            $this->value($row['metric'], $row['value']);
            if (MetricRegistry::get($row['metric'])->unit === 'currency') {
                $context = $this->context($row['context'] ?? []);
                if (array_keys($context) !== ['currency'] || ! is_string($context['currency']) || ! preg_match('/^[A-Z]{3}$/D', $context['currency'])) {
                    throw new InvalidArgumentException('Invalid metric currency.');
                }
            } elseif (isset($row['context'])) {
                $allowedContext = ['type', 'url', 'audited_url', 'device', 'lighthouse_version', 'tested_at', 'field_status', 'history_status', 'scope', 'percentile', 'collection_start', 'collection_end'];
                if ($source->kind !== 'pagespeed' || array_diff(array_keys($row['context']), $allowedContext)) {
                    throw new InvalidArgumentException('Invalid metric context.');
                }
                $this->context($row['context']);
            }
            if (isset($row['window_start']) || isset($row['window_end'])) {
                if ($source->kind !== 'pagespeed' || ! isset($row['window_start'], $row['window_end'])
                    || CarbonImmutable::parse($row['window_start'])->gt(CarbonImmutable::parse($row['window_end']))) {
                    throw new InvalidArgumentException('Invalid observation window.');
                }
            }
        }
        foreach ($result->periods as $row) {
            if (! in_array($row['metric'], $allowed, true) || MetricRegistry::get($row['metric'])->aggregation !== 'period') {
                throw new InvalidArgumentException('Invalid provider period metric.');
            }$this->date($row['start_date']);
            $this->date($row['end_date']);
            $this->value($row['metric'], $row['value']);
            if ($row['start_date'] > $row['end_date']) {
                throw new InvalidArgumentException('Invalid metric period.');
            }
        }
        foreach ($result->replaceDailyDates as $date => $metrics) {
            $this->date($date);
            foreach ($metrics as $metric) {
                if (! in_array($metric, $allowed, true) || ! MetricRegistry::get($metric)->snapshot) {
                    throw new InvalidArgumentException('Only observations can be replaced.');
                }
            }
        }

        foreach ($result->queries ?? [] as $row) {
            $this->query($source, $row);
        }
        $this->queryMonths($source, $result->queryMonths);
        $this->breakdowns($source, $result);
        $this->breakdownMonths($source, $result->breakdownMonths);

        return DB::transaction(function () use ($source, $result, $now) {
            $daily = $result->daily;
            if ($result->sitemapUrls !== null) {
                $daily[] = app(SitemapTracking::class)->record($source, $result->sitemapUrls, $now);
            }
            foreach ($result->replaceDailyDates as $date => $metrics) {
                $present = array_column(array_filter($result->daily, fn ($r) => $r['date'] === $date), 'metric');
                DailyMetric::query()->where('source_id', $source->id)->whereDate('date', $date)->whereIn('metric', $metrics)->whereNotIn('metric', $present)->delete();
            }
            foreach ($daily as $row) {
                $metric = DailyMetric::query()->firstOrNew(['source_id' => $source->id, 'metric' => $row['metric'], 'date' => $row['date']]);
                $changed = $metric->exists && (float) $metric->value !== (float) $row['value'];
                $metric->fill(['value' => $row['value'], 'is_partial' => $row['is_partial'], 'observed_at' => $now, 'context' => $row['context'] ?? null]);
                if (! $metric->exists) {
                    $metric->collected_at = $now;
                } elseif ($changed) {
                    $metric->revised_at = $now;
                }
                if (isset($row['window_start'], $row['window_end'])) {
                    $metric->window_start = CarbonImmutable::parse($row['window_start']);
                    $metric->window_end = CarbonImmutable::parse($row['window_end']);
                }
                if (MetricRegistry::get($row['metric'])->snapshot && $source->kind === 'uptimerobot') {
                    $metric->window_start = $now->subDay();
                    $metric->window_end = $now;
                }
                $metric->save();
            }
            foreach ($result->periods as $row) {
                PeriodMetric::query()->updateOrCreate(['source_id' => $source->id, 'metric' => $row['metric'], 'start_date' => $row['start_date'], 'end_date' => $row['end_date']], ['value' => $row['value'], 'is_partial' => $row['is_partial'], 'collected_at' => $now]);
            }
            $origins = ProviderCatalog::eventOrigins($source->kind);
            foreach ($result->events as $event) {
                if (! in_array($event['origin'], $origins, true) || strlen($event['title']) > 200) {
                    throw new InvalidArgumentException('Invalid provider event.');
                }
                $url = $event['url'];
                if ($url) {
                    $parts = parse_url($url);
                    if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
                        throw new InvalidArgumentException('Invalid provider event URL.');
                    }
                }
                $identity = ['source_id' => $source->id, 'origin' => $event['origin'], 'external_id' => (string) $event['external_id']];
                if (($event['occurred_at'] ?? null) !== null) {
                    $at = CarbonImmutable::parse($event['occurred_at'])->utc();
                } else {
                    $stored = ProjectEvent::query()->where($identity)->first();
                    $at = $stored instanceof ProjectEvent ? $stored->occurred_at : $now;
                }
                ProjectEvent::query()->updateOrCreate($identity, ['project_id' => $source->project_id, 'date' => $at->toDateString(), 'occurred_at' => $at, 'title' => $event['title'], 'body' => $event['body'] ?? '', 'url' => $event['url']]);
            }
            foreach ($result->authoritativeEvents as $origin => $since) {
                if (! in_array($origin, $origins, true)) {
                    throw new InvalidArgumentException('Invalid provider event.');
                }
                // Only the window that was read is authoritative; older imported events stay.
                $kept = array_column(array_filter($result->events, fn (array $event): bool => $event['origin'] === $origin), 'external_id');
                ProjectEvent::query()->where('source_id', $source->id)->where('origin', $origin)->whereNotIn('external_id', $kept)
                    ->when($since !== null, fn ($query) => $query->where('occurred_at', '>=', CarbonImmutable::parse($since)->utc()))->delete();
            }
            if ($result->queries !== null) {
                SearchQuery::query()->where('source_id', $source->id)->delete();
                foreach (array_chunk($result->queries, 200) as $chunk) {
                    SearchQuery::query()->insert(array_map(fn (array $row): array => ['workspace_id' => $source->workspace_id, 'source_id' => $source->id, 'days' => $row['days'], 'previous' => $row['previous'],
                        'start_date' => $row['start_date'], 'end_date' => $row['end_date'], 'query' => $row['query'], 'clicks' => $row['clicks'], 'impressions' => $row['impressions'],
                        'position' => $row['position'], 'is_partial' => $row['is_partial'], 'collected_at' => $now], $chunk));
                }
            }
            foreach ($result->breakdownMonths as $month) {
                if (BreakdownMonth::query()->where('source_id', $source->id)->where('dimension', $month['dimension'])->whereDate('month', $month['month'])->exists()) {
                    continue;
                }
                $archive = BreakdownMonth::query()->create(['source_id' => $source->id, 'dimension' => $month['dimension'], 'month' => $month['month'],
                    'start_date' => $month['start_date'], 'end_date' => $month['end_date'], 'item_count' => count($month['rows']), 'is_partial' => $month['is_partial'], 'collected_at' => $now]);
                foreach (array_chunk($month['rows'], 200) as $chunk) {
                    BreakdownMonthRow::query()->insert(array_map(fn (array $row): array => ['breakdown_month_id' => $archive->id, 'item' => $row['item'],
                        'visits' => $row['visits'], 'visitors' => $row['visitors'], 'conversions' => $row['conversions']], $chunk));
                }
            }
            foreach ($result->queryMonths as $month) {
                // Archived months are final; a month that is already stored stays as it is.
                if (SearchQueryMonth::query()->where('source_id', $source->id)->whereDate('month', $month['month'])->exists()) {
                    continue;
                }
                $archive = SearchQueryMonth::query()->create(['source_id' => $source->id, 'month' => $month['month'], 'start_date' => $month['start_date'], 'end_date' => $month['end_date'],
                    'weeks' => $month['weeks'], 'query_count' => count($month['rows']), 'collected_at' => $now]);
                foreach (array_chunk($month['rows'], 200) as $chunk) {
                    SearchQueryMonthRow::query()->insert(array_map(fn (array $row): array => ['search_query_month_id' => $archive->id, 'query' => $row['query'],
                        'clicks' => $row['clicks'], 'impressions' => $row['impressions'], 'position' => $row['position']], $chunk));
                }
            }
            foreach ($result->channelDays ?? [] as $day) {
                ChannelDay::query()->where('source_id', $source->id)->whereDate('date', $day['date'])->delete();
                ChannelDay::query()->insert(array_map(fn (string $channel, float $visits): array => ['workspace_id' => $source->workspace_id, 'source_id' => $source->id,
                    'date' => $day['date'], 'channel' => $channel, 'visits' => $visits, 'is_partial' => $day['is_partial'], 'collected_at' => $now], array_keys($day['visits']), array_values($day['visits'])));
            }
            if ($result->breakdownDimensions !== []) {
                BreakdownRow::query()->where('source_id', $source->id)->whereIn('dimension', $result->breakdownDimensions)->delete();
                foreach (array_chunk($result->breakdowns, 200) as $chunk) {
                    BreakdownRow::query()->insert(array_map(fn (array $row): array => ['workspace_id' => $source->workspace_id, 'source_id' => $source->id, 'dimension' => $row['dimension'],
                        'days' => $row['days'], 'previous' => $row['previous'], 'start_date' => $row['start_date'], 'end_date' => $row['end_date'], 'item' => $row['item'],
                        'visits' => $row['visits'], 'visitors' => $row['visitors'], 'conversions' => $row['conversions'], 'is_partial' => $row['is_partial'], 'collected_at' => $now], $chunk));
                }
            }
            $observed = DailyMetric::query()->where('source_id', $source->id)->max('date');
            $finalized = DailyMetric::query()->where('source_id', $source->id)->where('is_partial', false)->whereNotIn('metric', array_keys(array_filter(MetricRegistry::all(), fn ($definition) => $definition->snapshot)))->max('date');
            $source->forceFill(['latest_data_date' => $observed, 'finalized_through' => $finalized] + ($result->breakdownDimensions !== [] ? ['breakdowns_collected_at' => $now] : []))->save();

            return count($daily) + count($result->periods);
        });
    }
}
