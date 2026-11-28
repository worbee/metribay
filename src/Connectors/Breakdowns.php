<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use Closure;

/**
 * Analytics breakdowns shared by the website analytics connectors: one channel list for every provider, device and
 * country keys, the reporting windows, and collection that keeps a failing dimension's stored rows instead of failing
 * the data source. See docs/PROVIDERS.md (Audience breakdowns).
 */
final class Breakdowns
{
    /** Metribay's acquisition channels, in display order. Provider channel names map onto these. */
    public const CHANNELS = ['direct', 'organic_search', 'paid_search', 'organic_social', 'paid_social', 'email', 'referral', 'ai_assistant', 'organic_video',
        'shopping', 'display', 'affiliates', 'campaign', 'other_paid', 'other'];

    /** Dimensions stored per reporting window. Channels are stored per day, except where a provider only reports windows. */
    public const DIMENSIONS = ['channel', 'referrer', 'landing_page', 'campaign', 'country', 'device', 'goal'];

    public const DEVICES = ['desktop', 'mobile', 'tablet', 'other'];

    /** Reporting windows; each is stored with the window of the same length before it. */
    public const WINDOWS = [7, 30, 90];

    /** Items kept per dimension and window. */
    public const LIMIT = 100;

    /** Closed months whose top lists are read in one collection; each costs one request per dimension. */
    public const MONTHS_PER_COLLECTION = 1;

    /** Provider names, lower case without spaces or punctuation, and the Metribay channel they mean. */
    private const CHANNEL_NAMES = [
        'direct' => 'direct', 'directentry' => 'direct', 'none' => 'direct',
        'organicsearch' => 'organic_search', 'search' => 'organic_search', 'searchengine' => 'organic_search', 'searchengines' => 'organic_search',
        'paidsearch' => 'paid_search',
        'organicsocial' => 'organic_social', 'social' => 'organic_social', 'socials' => 'organic_social', 'socialnetwork' => 'organic_social', 'socialnetworks' => 'organic_social',
        'paidsocial' => 'paid_social',
        'email' => 'email', 'mail' => 'email',
        'referral' => 'referral', 'website' => 'referral', 'websites' => 'referral',
        'aiassistant' => 'ai_assistant', 'aiassistants' => 'ai_assistant', 'ai' => 'ai_assistant', 'llm' => 'ai_assistant',
        'organicvideo' => 'organic_video', 'video' => 'organic_video',
        'organicshopping' => 'shopping', 'paidshopping' => 'shopping', 'shopping' => 'shopping',
        'display' => 'display',
        'affiliate' => 'affiliates', 'affiliates' => 'affiliates',
        'campaign' => 'campaign', 'campaigns' => 'campaign',
        'paidvideo' => 'other_paid', 'paidother' => 'other_paid', 'paidunknown' => 'other_paid', 'paidads' => 'other_paid', 'crossnetwork' => 'other_paid',
        'audio' => 'other_paid', 'sms' => 'other_paid', 'push' => 'other_paid', 'mobilepushnotifications' => 'other_paid',
    ];

    /** The Metribay channel of a provider's channel name; unknown and unassigned names are "other". */
    public static function channel(string $name): string
    {
        return self::CHANNEL_NAMES[preg_replace('/[^a-z]/', '', strtolower($name)) ?? ''] ?? 'other';
    }

    /** Device class of a provider's device name. Laptops count as desktop, phones of every size as mobile. */
    public static function device(string $name): string
    {
        $name = preg_replace('/[^a-z]/', '', strtolower($name)) ?? '';

        return match (true) {
            in_array($name, ['desktop', 'laptop', 'computer'], true) => 'desktop',
            in_array($name, ['mobile', 'smartphone', 'phablet', 'featurephone', 'phone'], true) => 'mobile',
            $name === 'tablet' => 'tablet',
            default => 'other',
        };
    }

    /** An ISO 3166-1 alpha-2 country code in upper case, or "unknown". */
    public static function country(string $code): string
    {
        $code = strtoupper(trim($code));

        return preg_match('/^[A-Z]{2}$/D', $code) && ! in_array($code, ['XX', 'ZZ', 'AA'], true) ? $code : 'unknown';
    }

    /**
     * The reporting windows ending today: 7, 30 and 90 days and the window before each.
     *
     * @return list<array{days: int, previous: bool, from: CarbonImmutable, end: CarbonImmutable}>
     */
    public static function windows(CarbonImmutable $today): array
    {
        $windows = [];
        foreach (self::WINDOWS as $days) {
            foreach ([false, true] as $previous) {
                $end = $previous ? $today->subDays($days) : $today;
                $windows[] = ['days' => $days, 'previous' => $previous, 'from' => $end->subDays($days - 1), 'end' => $end];
            }
        }

        return $windows;
    }

    /**
     * A fetcher that asks the provider once per window.
     *
     * @param  Closure(CarbonImmutable, CarbonImmutable): list<array<string, mixed>>  $fetch
     * @return Closure(list<array{days: int, previous: bool, from: CarbonImmutable, end: CarbonImmutable}>): array<int, list<array<string, mixed>>>
     */
    public static function perWindow(Closure $fetch): Closure
    {
        return function (array $windows) use ($fetch): array {
            $rows = [];
            foreach ($windows as $index => $window) {
                $rows[$index] = $fetch($window['from'], $window['end']);
            }

            return $rows;
        };
    }

    /**
     * Reads every dimension for every window. A dimension that fails, or that the provider does not support, keeps
     * its stored rows; the error is reported so the collector can log it, and the data source's metrics are unaffected.
     *
     * @param  array<string, Closure(list<array{days: int, previous: bool, from: CarbonImmutable, end: CarbonImmutable}>): array<int, list<array<string, mixed>>>>  $fetchers
     * @param  int  $provisionalDays  Trailing days the provider may still revise (today only, or GA4's last three days).
     * @return array{rows: list<array<string, mixed>>, dimensions: list<string>, errors: array<string, string>}
     */
    public static function collect(array $fetchers, CarbonImmutable $today, int $provisionalDays = 1): array
    {
        $windows = self::windows($today);
        $out = ['rows' => [], 'dimensions' => [], 'errors' => []];
        foreach ($fetchers as $dimension => $fetch) {
            try {
                $byWindow = $fetch($windows);
                $rows = [];
                foreach ($windows as $index => $window) {
                    foreach (self::top($dimension, $byWindow[$index] ?? []) as $row) {
                        $rows[] = $row + ['dimension' => $dimension, 'days' => $window['days'], 'previous' => $window['previous'],
                            'start_date' => $window['from']->toDateString(), 'end_date' => $window['end']->toDateString(),
                            'is_partial' => $window['end'] > $today->subDays($provisionalDays)];
                    }
                }
                array_push($out['rows'], ...$rows);
                $out['dimensions'][] = $dimension;
            } catch (ConnectorException $exception) {
                $out['errors'][$dimension] = $exception->getMessage();
            }
        }

        return $out;
    }

    /**
     * Top lists for closed calendar months, archived once (breakdown_months). Only a month whose days are all final
     * is read, so the archive never keeps provisional figures. A dimension that fails is left out of that month.
     *
     * @param  array<string, Closure(list<array{days: int, previous: bool, from: CarbonImmutable, end: CarbonImmutable}>): array<int, list<array<string, mixed>>>>  $fetchers
     * @param  list<string>  $months  Months (Y-m) not archived yet.
     * @return array{months: list<array{dimension: string, month: string, start_date: string, end_date: string, is_partial: bool, rows: list<array{item: string, visits: float|null, visitors: float|null, conversions: float|null}>}>, errors: array<string, string>}
     */
    public static function months(array $fetchers, array $months, CarbonImmutable $today, int $provisionalDays = 1): array
    {
        $out = ['months' => [], 'errors' => []];
        $final = $today->subDays($provisionalDays);
        foreach (array_slice(array_values(array_unique($months)), 0, self::MONTHS_PER_COLLECTION) as $month) {
            if (preg_match('/^\d{4}-\d{2}$/D', $month) !== 1) {
                continue;
            }
            $from = CarbonImmutable::parse($month.'-01', $today->getTimezone())->startOfDay();
            $end = $from->endOfMonth()->startOfDay();
            if ($end->gte($final)) {
                continue;
            }
            $window = [['days' => $from->daysInMonth, 'previous' => false, 'from' => $from, 'end' => $end]];
            foreach ($fetchers as $dimension => $fetch) {
                try {
                    $rows = self::top($dimension, $fetch($window)[0] ?? []);
                } catch (ConnectorException $exception) {
                    $out['errors']['month:'.$dimension] = $exception->getMessage();

                    continue;
                }
                $out['months'][] = ['dimension' => $dimension, 'month' => $from->toDateString(), 'start_date' => $from->toDateString(), 'end_date' => $end->toDateString(), 'is_partial' => false, 'rows' => $rows];
            }
        }

        return $out;
    }

    /**
     * Visits per channel and date: provider channels that map to the same Metribay channel are added together.
     *
     * @param  list<array{date: string, channel: string, visits: float}>  $rows
     * @param  list<string>  $dates  Dates the provider answered for; a date without rows had no visits.
     * @return list<array{date: string, visits: array<string, float>, is_partial: bool}>
     */
    public static function channelDays(array $rows, array $dates, CarbonImmutable $today, int $provisionalDays = 1): array
    {
        $days = array_fill_keys($dates, []);
        foreach ($rows as $row) {
            if (! array_key_exists($row['date'], $days)) {
                throw new ConnectorException('The provider returned channel visits for an unexpected date.');
            }
            $channel = self::channel($row['channel']);
            $days[$row['date']][$channel] = ($days[$row['date']][$channel] ?? 0.0) + $row['visits'];
        }
        ksort($days);
        $out = [];
        $provisional = $today->subDays($provisionalDays)->toDateString();
        foreach ($days as $date => $visits) {
            $out[] = ['date' => $date, 'visits' => array_filter($visits, fn (float $value): bool => $value > 0), 'is_partial' => $date > $provisional];
        }

        return $out;
    }

    /**
     * Normalized, merged and ranked items of one dimension and window.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{item: string, visits: float|null, visitors: float|null, conversions: float|null}>
     */
    private static function top(string $dimension, array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $item = is_string($row['item'] ?? null) ? trim($row['item']) : '';
            $item = match ($dimension) {
                'channel' => self::channel($item),
                'device' => self::device($item),
                'country' => self::country($item),
                default => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $item) ?? '', 0, 1000),
            };
            if ($item === '') {
                continue;
            }
            $values = [];
            foreach (['visits', 'visitors', 'conversions'] as $measure) {
                $value = $row[$measure] ?? null;
                $values[$measure] = is_int($value) || is_float($value) ? (float) $value : null;
            }
            if (isset($items[$item])) {
                // Two provider items became one: visits and conversions add up, visitors would count people twice.
                foreach (['visits', 'conversions'] as $measure) {
                    $items[$item][$measure] = $items[$item][$measure] === null && $values[$measure] === null ? null : ($items[$item][$measure] ?? 0.0) + ($values[$measure] ?? 0.0);
                }
                $items[$item]['visitors'] = null;

                continue;
            }
            $items[$item] = ['item' => $item] + $values;
        }
        $rank = fn (array $row): float => $dimension === 'goal' ? ($row['conversions'] ?? $row['visitors'] ?? 0.0) : ($row['visits'] ?? $row['visitors'] ?? 0.0);
        $items = array_values(array_filter($items, fn (array $row): bool => $rank($row) > 0));
        usort($items, fn (array $a, array $b): int => [$rank($b), $a['item']] <=> [$rank($a), $b['item']]);

        return array_slice($items, 0, self::LIMIT);
    }
}
