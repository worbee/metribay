<?php

declare(strict_types=1);

namespace Metribay\Core\Demo;

/** Multipliers over time for sample series. $ago counts days back from today (0 = today). */
final class Shape
{
    /**
     * Growth through knots of [days ago, multiplier], oldest first, interpolated in log space so growth compounds.
     * Equal neighbouring multipliers make a plateau.
     *
     * @param  list<array{0: int, 1: float}>  $knots
     */
    public static function growth(int $ago, array $knots): float
    {
        if ($ago >= $knots[0][0]) {
            return $knots[0][1];
        }
        foreach ($knots as $index => [$at, $value]) {
            if ($ago >= $at) {
                [$previousAt, $previousValue] = $knots[$index - 1];
                $share = ($previousAt - $ago) / ($previousAt - $at);

                return exp(log($previousValue) + $share * (log($value) - log($previousValue)));
            }
        }

        return $knots[array_key_last($knots)][1];
    }

    /** A spike that starts $at days ago at 1 + $height and halves every $halfLife days. */
    public static function spike(int $ago, int $at, float $height, float $halfLife): float
    {
        return $ago > $at ? 1.0 : 1 + $height * 0.5 ** (($at - $ago) / $halfLife);
    }

    /** A lasting change that starts $at days ago and reaches $factor after $ramp days. */
    public static function step(int $ago, int $at, float $factor, int $ramp = 1): float
    {
        if ($ago > $at) {
            return 1.0;
        }

        return 1 + ($factor - 1) * min(1, ($at - $ago + 1) / max(1, $ramp));
    }
}
