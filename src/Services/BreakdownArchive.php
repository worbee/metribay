<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Metribay\Core\Connectors\Breakdowns;
use Metribay\Core\Models\BreakdownMonth;
use Metribay\Core\Models\BreakdownMonthRow;
use Metribay\Core\Models\ChannelDay;
use Metribay\Core\Models\Source;

/**
 * Calendar-month breakdowns. Channel days already exist per date, so a month can be summed from them.
 * Connectors archive the other dimensions for closed months (CollectionResult::$breakdownMonths).
 */
final class BreakdownArchive
{
    /**
     * Sums the month's channel days. $replace rewrites an existing month: the month under report follows late
     * collection until its report is sealed. Without channel days, an archive a connector wrote stays as it is.
     */
    public function channels(Source $source, string $month, bool $replace = false): void
    {
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth()->startOfDay();
        $existing = BreakdownMonth::query()->where('source_id', $source->id)->where('dimension', 'channel')->whereDate('month', $start->toDateString())->first();
        if ($existing instanceof BreakdownMonth && ! $replace) {
            return;
        }
        $days = ChannelDay::query()->where('source_id', $source->id)->where('date', '>=', $start->toDateString())->where('date', '<=', $end->toDateString())->get();
        if ($days->isEmpty()) {
            return;
        }
        $totals = [];
        foreach ($days as $day) {
            $channel = (string) $day->channel;
            if (in_array($channel, Breakdowns::CHANNELS, true)) {
                $totals[$channel] = ($totals[$channel] ?? 0) + (float) $day->visits;
            }
        }
        if ($totals === []) {
            return;
        }
        $partial = $days->contains(fn (ChannelDay $day): bool => (bool) $day->is_partial)
            || $days->map(fn (ChannelDay $day): string => $day->date->toDateString())->unique()->count() < $start->daysInMonth;
        DB::transaction(function () use ($source, $start, $end, $existing, $totals, $partial): void {
            if ($existing instanceof BreakdownMonth) {
                BreakdownMonthRow::query()->where('breakdown_month_id', $existing->id)->delete();
                $existing->delete();
            }
            $archive = BreakdownMonth::query()->create([
                'source_id' => $source->id, 'dimension' => 'channel', 'month' => $start->toDateString(),
                'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'item_count' => count($totals),
                'is_partial' => $partial, 'collected_at' => CarbonImmutable::now('UTC'),
            ]);
            BreakdownMonthRow::query()->insert(array_map(fn (string $channel, float $visits): array => [
                'breakdown_month_id' => $archive->id, 'item' => $channel, 'visits' => $visits, 'visitors' => null, 'conversions' => null,
            ], array_keys($totals), array_values($totals)));
        });
    }

    /**
     * The archived month's largest items per dimension.
     *
     * @return list<array{dimension: string, partial: bool, items: list<array{item: string, visits: ?float, visitors: ?float, conversions: ?float}>}>
     */
    public function forMonth(Source $source, string $month): array
    {
        $start = CarbonImmutable::parse($month.'-01')->toDateString();
        $archives = BreakdownMonth::query()->where('source_id', $source->id)->whereDate('month', $start)->orderBy('dimension')->with('rows')->get();
        $reports = [];
        foreach ($archives as $archive) {
            $items = [];
            foreach ($archive->rows as $row) {
                $items[] = [
                    'item' => (string) $row->item,
                    'visits' => $row->visits === null ? null : (float) $row->visits,
                    'visitors' => $row->visitors === null ? null : (float) $row->visitors,
                    'conversions' => $row->conversions === null ? null : (float) $row->conversions,
                ];
            }
            $rank = fn (array $item): float => $item['visits'] ?? $item['visitors'] ?? $item['conversions'] ?? -1.0;
            usort($items, fn (array $a, array $b): int => [$rank($b), $a['item']] <=> [$rank($a), $b['item']]);
            $reports[] = ['dimension' => (string) $archive->dimension, 'partial' => (bool) $archive->is_partial, 'items' => array_slice($items, 0, 8)];
        }

        return $reports;
    }
}
