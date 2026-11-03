<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Metrics\MetricRegistry;
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

    public function persist(Source $source, CollectionResult $result, CarbonImmutable $now): int
    {
        $allowed = ProviderCatalog::metricKeys($source->kind);
        foreach ($result->daily as $row) {
            if (! in_array($row['metric'], $allowed, true)) {
                throw new InvalidArgumentException('Metric does not belong to this provider.');
            }$this->date($row['date']);
            $this->value($row['metric'], $row['value']);
            if (isset($row['context'])) {
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

        return DB::transaction(function () use ($source, $result, $now) {
            foreach ($result->replaceDailyDates as $date => $metrics) {
                $present = array_column(array_filter($result->daily, fn ($r) => $r['date'] === $date), 'metric');
                DailyMetric::query()->where('source_id', $source->id)->whereDate('date', $date)->whereIn('metric', $metrics)->whereNotIn('metric', $present)->delete();
            }
            foreach ($result->daily as $row) {
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
                $at = CarbonImmutable::parse($event['occurred_at'])->utc();
                ProjectEvent::query()->updateOrCreate(['source_id' => $source->id, 'origin' => $event['origin'], 'external_id' => (string) $event['external_id']], ['project_id' => $source->project_id, 'date' => $at->toDateString(), 'occurred_at' => $at, 'title' => $event['title'], 'body' => $event['body'] ?? '', 'url' => $event['url']]);
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
            $observed = DailyMetric::query()->where('source_id', $source->id)->max('date');
            $finalized = DailyMetric::query()->where('source_id', $source->id)->where('is_partial', false)->whereNotIn('metric', array_keys(array_filter(MetricRegistry::all(), fn ($definition) => $definition->snapshot)))->max('date');
            $source->forceFill(['latest_data_date' => $observed, 'finalized_through' => $finalized])->save();

            return count($result->daily) + count($result->periods);
        });
    }
}
