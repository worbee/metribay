<?php

namespace Metribay\Core\Metrics;

use Carbon\CarbonImmutable;

/**
 * The reporting period of the overview and project pages: the last 7, 30 or 90 days up to today, yesterday, or a
 * custom range of calendar dates. Every period compares with the same number of days just before it. Dates are read
 * in each data source's reporting timezone. See docs/PROJECT_PAGE.md.
 */
final readonly class Period
{
    /** Days that end today. */
    public const PRESETS = [7, 30, 90];

    /** `days` value of yesterday: one complete day. */
    public const YESTERDAY = 1;

    /** Longest custom range: two years of daily points. */
    public const MAX_DAYS = 731;

    public const EARLIEST = '2000-01-01';

    private function __construct(public int $days, public ?string $from = null, public ?string $to = null) {}

    public static function preset(int $days): self
    {
        if (! in_array($days, [self::YESTERDAY, ...self::PRESETS], true)) {
            throw new \InvalidArgumentException('Unknown reporting period.');
        }

        return new self($days);
    }

    /** A custom range of calendar dates, both included; null when the dates are invalid or outside the limits. */
    public static function custom(mixed $from, mixed $to, ?CarbonImmutable $now = null): ?self
    {
        if (! self::isDate($from) || ! self::isDate($to) || $from > $to || $from < self::EARLIEST) {
            return null;
        }
        // The furthest-ahead timezones already have tomorrow's date.
        $latest = ($now ?? CarbonImmutable::now('UTC'))->utc()->addDay()->toDateString();
        $days = (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;

        return $to <= $latest && $days <= self::MAX_DAYS ? new self($days, $from, $to) : null;
    }

    /** From request input: `from` and `to` make a custom range, otherwise `days`; null for anything invalid. */
    public static function fromInput(mixed $days, mixed $from = null, mixed $to = null): ?self
    {
        if (($from !== null && $from !== '') || ($to !== null && $to !== '')) {
            return self::custom($from, $to);
        }
        $days = is_numeric($days) ? (int) $days : null;

        return in_array($days, [self::YESTERDAY, ...self::PRESETS], true) ? new self($days) : null;
    }

    public function isCustom(): bool
    {
        return $this->from !== null;
    }

    public function isYesterday(): bool
    {
        return ! $this->isCustom() && $this->days === self::YESTERDAY;
    }

    /** The 7, 30 or 90 days that end today. */
    public function isPreset(): bool
    {
        return ! $this->isCustom() && in_array($this->days, self::PRESETS, true);
    }

    /**
     * First and last date of the period in a reporting timezone, both included.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(string $timezone, CarbonImmutable $now): array
    {
        if ($this->isCustom()) {
            return [CarbonImmutable::parse($this->from, $timezone), CarbonImmutable::parse($this->to, $timezone)];
        }
        $today = $now->setTimezone($timezone)->startOfDay();
        $end = $this->isYesterday() ? $today->subDay() : $today;

        return [$end->subDays($this->days - 1), $end];
    }

    /**
     * The same number of days just before the period.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function previousWindow(string $timezone, CarbonImmutable $now): array
    {
        $end = $this->window($timezone, $now)[0]->subDay();

        return [$end->subDays($this->days - 1), $end];
    }

    /** Earliest date any data source may need, previous period included, with a day of timezone margin on each side. */
    public function earliestDate(CarbonImmutable $now): string
    {
        return $this->previousWindow('UTC', $now)[0]->subDays(2)->toDateString();
    }

    /** Last date any data source may need. */
    public function latestDate(CarbonImmutable $now): string
    {
        return $this->window('UTC', $now)[1]->addDays(2)->toDateString();
    }

    /** @return array{days?: int, from?: string, to?: string} Route parameters that keep this period on another page. */
    public function query(): array
    {
        return $this->isCustom() ? ['from' => $this->from, 'to' => $this->to] : ['days' => $this->days];
    }

    /** Label of the period, such as "Last 30 days", "Yesterday" or "1 Oct – 15 Oct 2026". */
    public function label(): string
    {
        if ($this->isYesterday()) {
            return __('Yesterday');
        }
        if (! $this->isCustom()) {
            return __('Last :days days', ['days' => $this->days]);
        }
        $from = CarbonImmutable::parse($this->from);
        $to = CarbonImmutable::parse($this->to);
        if ($this->from === $this->to) {
            return $to->translatedFormat('j M Y');
        }

        return $from->translatedFormat($from->year === $to->year ? 'j M' : 'j M Y').' – '.$to->translatedFormat('j M Y');
    }

    /** The calendar month (Y-m) the custom range covers exactly, if any. */
    public function calendarMonth(): ?string
    {
        if (! $this->isCustom()) {
            return null;
        }
        $from = CarbonImmutable::parse($this->from);

        return $from->day === 1 && $from->endOfMonth()->toDateString() === $this->to ? $from->format('Y-m') : null;
    }

    private static function isDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }
}
