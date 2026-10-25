<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Metrics\MetricRegistry;
use Metribay\Core\Models\Annotation;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\PeriodMetric;
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
        if (! is_finite((float) $value) || $value < 0 || ($definition->unit === 'percent' && $value > 100) || ($definition->unit === 'status' && ! in_array($value, [0, 1, 2, 8, 9, 0.0, 1.0, 2.0, 8.0, 9.0], true))) {
            throw new InvalidArgumentException('Metric value is outside its valid range.');
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
                if (! MetricRegistry::get($metric)->snapshot) {
                    throw new InvalidArgumentException('Only observations can be replaced.');
                }
            }
        }

        return DB::transaction(function () use ($source, $result, $now) {
            foreach ($result->replaceDailyDates as $date => $metrics) {
                $present = array_column(array_filter($result->daily, fn ($r) => $r['date'] === $date), 'metric');
                DailyMetric::query()->where('source_id', $source->id)->whereDate('date', $date)->whereIn('metric', $metrics)->whereNotIn('metric', $present)->delete();
            }
            foreach ($result->daily as $row) {
                $metric = DailyMetric::query()->firstOrNew(['source_id' => $source->id, 'metric' => $row['metric'], 'date' => $row['date']]);
                $changed = $metric->exists && (float) $metric->value !== (float) $row['value'];
                $metric->fill(['value' => $row['value'], 'is_partial' => $row['is_partial'], 'observed_at' => $now]);
                if (! $metric->exists) {
                    $metric->collected_at = $now;
                } elseif ($changed) {
                    $metric->revised_at = $now;
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
            foreach ($result->annotations as $event) {
                if (strlen($event['title']) > 200) {
                    throw new InvalidArgumentException('Invalid provider annotation.');
                }
                $url = $event['url'];
                if ($url) {
                    $parts = parse_url($url);
                    if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
                        throw new InvalidArgumentException('Invalid provider annotation URL.');
                    }
                }
                $at = CarbonImmutable::parse($event['occurred_at'])->utc();
                Annotation::query()->updateOrCreate(['source_id' => $source->id, 'origin' => 'github_release', 'external_id' => (string) $event['external_id']], ['project_id' => $source->project_id, 'date' => $at->toDateString(), 'occurred_at' => $at, 'title' => $event['title'], 'body' => $event['body'] ?? '', 'url' => $event['url']]);
            }
            if ($result->authoritativeAnnotations) {
                Annotation::query()->where('source_id', $source->id)->where('origin', 'github_release')->whereNotIn('external_id', array_column($result->annotations, 'external_id'))->delete();
            }
            $observed = DailyMetric::query()->where('source_id', $source->id)->max('date');
            $finalized = DailyMetric::query()->where('source_id', $source->id)->where('is_partial', false)->whereNotIn('metric', ['monitor_status', 'uptime_24h', 'response_time_24h', 'open_issues'])->max('date');
            $source->forceFill(['latest_data_date' => $observed, 'finalized_through' => $finalized])->save();

            return count($result->daily) + count($result->periods);
        });
    }
}
