<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Metribay\Core\Models\BreakdownMonth;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\PeriodMetric;
use Metribay\Core\Models\Source;

/** What an analytics connector reads for the monthly report besides its rolling windows (SourceConfiguration). */
final class ReportMonths
{
    /**
     * Closed calendar months the monthly report still needs from an analytics provider, newest first: unique totals that
     * are missing or provisional for up to 14 months back (the month a year ago and the one before it), and top lists
     * for the last two months that are not archived yet.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    public function toRead(Source $source, CarbonImmutable $now, bool $lists): array
    {
        if (! in_array($source->kind, ProviderCatalog::BREAKDOWN_PROVIDERS, true)) {
            return [[], []];
        }
        $first = DailyMetric::query()->where('source_id', $source->id)->min('date');
        if (! is_string($first)) {
            return [[], []];
        }
        $current = $now->setTimezone($source->reporting_timezone ?: 'UTC')->startOfMonth();
        $earliest = CarbonImmutable::parse(substr($first, 0, 7).'-01', $current->getTimezone())->max($current->subMonths(14));
        $final = PeriodMetric::query()->where('source_id', $source->id)->whereIn('metric', ['visitors', 'active_users'])->where('is_partial', false)
            ->where('start_date', '>=', $earliest->toDateString())->get(['start_date', 'end_date'])
            ->filter(fn (PeriodMetric $row): bool => $row->start_date->day === 1 && $row->end_date->toDateString() === $row->start_date->endOfMonth()->toDateString())
            ->map(fn (PeriodMetric $row): string => $row->start_date->format('Y-m'))->unique()->all();
        $periods = [];
        for ($month = $current->subMonth(); $month->gte($earliest); $month = $month->subMonth()) {
            if (! in_array($month->format('Y-m'), $final, true)) {
                $periods[] = $month->format('Y-m');
            }
        }
        $breakdowns = [];
        if ($lists) {
            $archived = BreakdownMonth::query()->where('source_id', $source->id)->where('dimension', '!=', 'channel')
                ->whereDate('month', '>=', $current->subMonths(2)->toDateString())->pluck('month')->map(fn (CarbonImmutable $month): string => $month->format('Y-m'))->all();
            foreach ([$current->subMonth(), $current->subMonths(2)] as $month) {
                if ($month->gte($earliest) && ! in_array($month->format('Y-m'), $archived, true)) {
                    $breakdowns[] = $month->format('Y-m');
                }
            }
        }

        return [$periods, $breakdowns];
    }
}
