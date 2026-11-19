<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Locale;
use Metribay\Core\Connectors\Breakdowns;
use Metribay\Core\Metrics\Period;
use Metribay\Core\Models\BreakdownRow;
use Metribay\Core\Models\ChannelDay;
use Metribay\Core\Models\DailyMetric;

/**
 * The project page's audience breakdown blocks: Acquisition (channels, referrers, landing pages, campaigns), Locations
 * and devices (countries, devices) and Goals. Channels stored per day follow the reporting period exactly; the other
 * lists, and Umami's channels, use the stored 7, 30 or 90-day window closest to it. See docs/PROJECT_PAGE.md.
 */
class AudienceBreakdowns
{
    /** Blocks (also their hidden-chart keys) and the dimensions each one shows as tabs. */
    public const BLOCKS = ['acquisition' => ['channel', 'referrer', 'landing_page', 'campaign'], 'locations' => ['country', 'device'], 'goals' => ['goal']];

    /** Rows shown before "Show all". */
    public const TOP = 10;

    /** Channels drawn separately in the daily chart; the rest are combined. */
    public const CHART_CHANNELS = 5;

    /** Interface names of Metribay's channels. */
    public const CHANNEL_LABELS = ['direct' => 'Direct', 'organic_search' => 'Organic search', 'paid_search' => 'Paid search', 'organic_social' => 'Organic social',
        'paid_social' => 'Paid social', 'email' => 'Email', 'referral' => 'Referral', 'ai_assistant' => 'AI assistants', 'organic_video' => 'Video',
        'shopping' => 'Shopping', 'display' => 'Display', 'affiliates' => 'Affiliates', 'campaign' => 'Campaigns', 'other_paid' => 'Other paid', 'other' => 'Other'];

    public const DEVICE_LABELS = ['desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet', 'other' => 'Other devices'];

    public const DIMENSION_LABELS = ['channel' => 'Channels', 'referrer' => 'Referrers', 'landing_page' => 'Landing pages', 'campaign' => 'Campaigns',
        'country' => 'Countries', 'device' => 'Devices', 'goal' => 'Goals'];

    public const BLOCK_LABELS = ['acquisition' => 'Acquisition', 'locations' => 'Locations and devices', 'goals' => 'Goals'];

    /**
     * Reports per data source and dimension. Dimensions without stored rows, and sources without breakdowns, are left out.
     *
     * @param  list<array<string, mixed>>  $sources  Project sources from DashboardData.
     * @return array<int, array<string, array<string, mixed>>>
     */
    public function forSources(array $sources, Period|int $period, ?CarbonImmutable $now = null): array
    {
        $period = $period instanceof Period ? $period : Period::preset($period);
        $now ??= CarbonImmutable::now('UTC');
        // Website analytics sources; a PostHog source that counts one event has no breakdowns.
        $sources = array_values(array_filter($sources, fn (array $source): bool => in_array($source['kind'], ProviderCatalog::BREAKDOWN_PROVIDERS, true)
            && in_array('visits', $source['metric_keys'] ?? ProviderCatalog::metricKeys($source['kind']), true)));
        if ($sources === []) {
            return [];
        }
        $ids = array_map(fn (array $source): int => (int) $source['id'], $sources);
        $days = SearchQueries::storedDays($period);
        $stored = BreakdownRow::query()->whereIn('source_id', $ids)->where('days', $days)->get()->groupBy(['source_id', 'dimension']);
        $earliest = $period->earliestDate($now);
        $latest = $period->latestDate($now);
        $channelDays = ChannelDay::query()->whereIn('source_id', $ids)->where('date', '>=', $earliest)->where('date', '<=', $latest)->get()->groupBy('source_id');
        $hasChannelDays = ChannelDay::query()->whereIn('source_id', $ids)->distinct()->pluck('source_id')->map(fn ($id): int => (int) $id)->all();
        $quiet = DailyMetric::query()->whereIn('source_id', $ids)->where('metric', 'visits')->where('value', 0)
            ->where('date', '>=', $earliest)->where('date', '<=', $latest)->get(['source_id', 'date'])->groupBy('source_id');
        $reports = [];
        foreach ($sources as $source) {
            $id = (int) $source['id'];
            $byDimension = $stored->get($id, collect());
            foreach (Breakdowns::DIMENSIONS as $dimension) {
                if ($dimension === 'channel' && in_array($id, $hasChannelDays, true)) {
                    $report = $this->channels($source, $period, $now, $channelDays->get($id, collect()), $quiet->get($id, collect()));
                } else {
                    $rows = $byDimension->get($dimension);
                    $report = $rows === null ? null : $this->windowed($source, $dimension, $rows, $days, ! $period->isPreset());
                }
                if ($report !== null) {
                    $reports[$id][$dimension] = $report;
                }
            }
        }

        return $reports;
    }

    /**
     * Channels from daily visits: the exact reporting period, compared with the period before, and a daily stacked chart.
     *
     * @param  array<string, mixed>  $source
     * @param  Collection<int, ChannelDay>  $rows
     * @param  Collection<int, DailyMetric>  $quiet  Dates with no visits at all: known, though no channel has a row.
     * @return array<string, mixed>
     */
    private function channels(array $source, Period $period, CarbonImmutable $now, Collection $rows, Collection $quiet): array
    {
        $timezone = (string) ($source['reporting_timezone'] ?? 'UTC');
        [$start, $end] = $period->window($timezone, $now);
        [$previousStart, $previousEnd] = $period->previousWindow($timezone, $now);
        $byDate = $rows->groupBy(fn (ChannelDay $row): string => $row->date->toDateString());
        $quietDates = $quiet->map(fn (DailyMetric $row): string => $row->date->toDateString())->flip();
        $sum = function (CarbonImmutable $from, CarbonImmutable $to) use ($byDate, $quietDates): array {
            $totals = [];
            $known = 0;
            $partial = false;
            for ($date = $from; $date <= $to; $date = $date->addDay()) {
                $day = $byDate->get($date->toDateString());
                if ($day === null) {
                    $known += $quietDates->has($date->toDateString()) ? 1 : 0;

                    continue;
                }
                $known++;
                foreach ($day as $row) {
                    $totals[$row->channel] = ($totals[$row->channel] ?? 0.0) + $row->visits;
                    $partial = $partial || $row->is_partial;
                }
            }

            return [$totals, $known, $partial];
        };
        [$current, $known, $partial] = $sum($start, $end);
        [$before, $knownBefore] = $sum($previousStart, $previousEnd);
        $total = array_sum($current);
        $list = [];
        foreach ($current as $channel => $visits) {
            $previous = $knownBefore > 0 ? ($before[$channel] ?? 0.0) : null;
            $list[] = ['item' => $channel, 'label' => __(self::CHANNEL_LABELS[$channel] ?? 'Other'), 'value' => $visits, 'visitors' => null,
                'share' => $total > 0 ? $visits / $total : null, 'change' => $previous !== null && $previous > 0 ? 100 * ($visits - $previous) / $previous : null];
        }
        $list = $this->sorted($list);
        // The daily chart draws the largest channels and combines the rest, in the same order as the table.
        $drawn = array_slice(array_column($list, 'item'), 0, self::CHART_CHANNELS);
        $series = [];
        for ($date = $start; $date <= $end; $date = $date->addDay()) {
            $day = $byDate->get($date->toDateString());
            $values = $day === null && ! $quietDates->has($date->toDateString()) ? null : array_fill_keys([...$drawn, 'rest'], 0.0);
            foreach ($day ?? [] as $row) {
                $values[in_array($row->channel, $drawn, true) ? $row->channel : 'rest'] += $row->visits;
            }
            $series[] = ['date' => $date->toDateString(), 'values' => $values, 'partial' => (bool) $day?->contains('is_partial', true)];
        }
        $datasets = array_map(fn (string $channel): array => ['key' => $channel, 'label' => __(self::CHANNEL_LABELS[$channel])], $drawn);
        if (count($list) > count($drawn)) {
            $datasets[] = ['key' => 'rest', 'label' => __('Other channels')];
        }

        return ['dimension' => 'channel', 'source' => $source, 'measure' => 'visits', 'daily' => true, 'rows' => $list, 'total' => $total,
            'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'days' => $period->days, 'fallback' => false, 'partial' => $partial,
            'coverage' => ['known' => $known, 'total' => $period->days], 'chart' => $total > 0 ? ['series' => $series, 'datasets' => $datasets] : null];
    }

    /**
     * One dimension from the stored window closest to the period, compared with the window before it.
     *
     * @param  array<string, mixed>  $source
     * @param  Collection<string, BreakdownRow>  $rows
     * @return array<string, mixed>|null
     */
    private function windowed(array $source, string $dimension, Collection $rows, int $days, bool $fallback): ?array
    {
        $current = $rows->where('previous', false);
        $first = $current->first();
        if (! $first instanceof BreakdownRow) {
            return null;
        }
        $before = $rows->where('previous', true)->keyBy('item');
        $measure = $dimension === 'goal' ? 'conversions' : ($current->every(fn (BreakdownRow $row): bool => $row->visits !== null) ? 'visits' : 'visitors');
        $total = $measure === 'visitors' ? null : (float) $current->sum($measure);
        $list = [];
        foreach ($current as $row) {
            $value = (float) ($row->{$measure} ?? 0);
            $previous = $before->get($row->item);
            $priorValue = $previous instanceof BreakdownRow ? $previous->{$measure} : null;
            $list[] = ['item' => $row->item, 'label' => $this->label($dimension, $row->item), 'value' => $value, 'visitors' => $measure === 'visitors' ? null : $row->visitors,
                // Visitors overlap between rows, so their share is of the largest row, not of a total.
                'share' => $total !== null && $total > 0 ? $value / $total : null,
                'change' => $priorValue !== null && $priorValue > 0 ? 100 * ($value - $priorValue) / $priorValue : null,
                'new' => $previous === null && $before->isNotEmpty()];
        }
        $list = $this->sorted($list);
        $max = $list === [] ? 0.0 : max(array_column($list, 'value'));
        foreach ($list as &$row) {
            $row['bar'] = $max > 0 ? $row['value'] / $max : 0.0;
        }
        unset($row);

        return ['dimension' => $dimension, 'source' => $source, 'measure' => $measure, 'daily' => false, 'rows' => $list, 'total' => $total,
            'start_date' => $first->start_date->toDateString(), 'end_date' => $first->end_date->toDateString(), 'days' => $days, 'fallback' => $fallback,
            'partial' => $current->contains('is_partial', true), 'coverage' => null, 'chart' => null, 'collected_at' => $first->collected_at];
    }

    /** The name an item is shown with: translated channels and devices, country names in the interface language. */
    public function label(string $dimension, string $item): string
    {
        return match ($dimension) {
            'channel' => __(self::CHANNEL_LABELS[$item] ?? 'Other'),
            'device' => __(self::DEVICE_LABELS[$item] ?? 'Other devices'),
            'country' => $item === 'unknown' ? __('Unknown') : $this->country($item),
            default => $item,
        };
    }

    private function country(string $code): string
    {
        $name = class_exists(Locale::class) ? Locale::getDisplayRegion('-'.$code, app()->getLocale()) : '';

        return is_string($name) && $name !== '' && $name !== $code ? $name : $code;
    }

    /**
     * Largest first; equal rows in alphabetical order.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sorted(array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => [$b['value'], $a['label']] <=> [$a['value'], $b['label']]);

        return $rows;
    }
}
