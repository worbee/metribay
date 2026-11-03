<?php

declare(strict_types=1);

namespace Metribay\Core\Demo;

use Carbon\CarbonImmutable;

/** Calendar effects shared by the sample projects: weekdays, holidays and yearly seasons. */
final class Calendar
{
    /** @param array<int, float> $days Multipliers by ISO weekday (1 = Monday); missing days count as 1. */
    public static function weekday(CarbonImmutable $date, float $weekend, array $days = []): float
    {
        return $days[$date->dayOfWeekIso] ?? ($date->isWeekend() ? $weekend : 1.0);
    }

    /**
     * How much quieter a holiday is: 1 on ordinary days, down to 1 - $depth at Christmas and New Year.
     * Covers the Christmas week, Easter and US Thanksgiving, which most sample audiences share.
     */
    public static function holiday(CarbonImmutable $date, float $depth): float
    {
        $md = $date->format('m-d');
        $share = match (true) {
            in_array($md, ['12-24', '12-25', '12-26', '12-31', '01-01'], true) => 1.0,
            $md >= '12-27' && $md <= '12-30' => 0.65,
            in_array($md, ['12-23', '01-02'], true) => 0.4,
            default => 0.0,
        };
        $easter = self::easter($date->year);
        if ($date->isSameDay($easter->subDays(2)) || $date->isSameDay($easter->addDay())) {
            $share = max($share, 0.5);
        }
        $thanksgiving = CarbonImmutable::create($date->year, 11, 1)->nthOfMonth(4, CarbonImmutable::THURSDAY);
        if ($thanksgiving && ($date->isSameDay($thanksgiving) || $date->isSameDay($thanksgiving->addDay()))) {
            $share = max($share, 0.35);
        }

        return 1 - $depth * $share;
    }

    /** A yearly wave: 1 + $amplitude on the peak day of the year, 1 - $amplitude half a year later. */
    public static function season(CarbonImmutable $date, float $amplitude, int $peakDay): float
    {
        return 1 + $amplitude * cos(2 * M_PI * ($date->dayOfYear - $peakDay) / 365.25);
    }

    /** Easter Sunday (anonymous Gregorian algorithm), without the calendar extension. */
    public static function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'UTC');
    }
}
