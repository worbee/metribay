<?php

declare(strict_types=1);

namespace Metribay\Core\Demo;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Seeded randomness for sample data. Every stream is derived from the seed and its own name, so adding a project or a
 * series never changes the numbers of another one.
 */
final class Noise
{
    private Randomizer $random;

    public function __construct(public readonly int $seed, public readonly string $stream)
    {
        $this->random = new Randomizer(new Xoshiro256StarStar(hash('sha256', $seed.'|'.$stream, true)));
    }

    public function fork(string $stream): self
    {
        return new self($this->seed, $this->stream.'/'.$stream);
    }

    /** A float in [0, 1). */
    public function float(): float
    {
        return $this->random->nextFloat();
    }

    public function between(float $min, float $max): float
    {
        return $min + ($max - $min) * $this->float();
    }

    public function int(int $min, int $max): int
    {
        return $this->random->getInt($min, $max);
    }

    public function chance(float $probability): bool
    {
        return $this->float() < $probability;
    }

    /** Standard normal (Box–Muller). */
    public function normal(): float
    {
        return sqrt(-2 * log(1 - $this->float())) * cos(2 * M_PI * $this->float());
    }

    public function poisson(float $lambda): int
    {
        if ($lambda <= 0) {
            return 0;
        }
        if ($lambda > 40) {
            return max(0, (int) round($lambda + sqrt($lambda) * $this->normal()));
        }
        $limit = exp(-$lambda);
        $count = 0;
        for ($product = $this->float(); $product > $limit; $product *= $this->float()) {
            $count++;
        }

        return $count;
    }

    /** A positive value whose median is $median; $sigma is the spread of its logarithm. */
    public function lognormal(float $median, float $sigma): float
    {
        return $median * exp($sigma * $this->normal());
    }

    /**
     * Multiplicative day-to-day wander, oldest day first: an AR(1) process in log space, so busy and quiet days cluster
     * instead of flickering independently.
     *
     * @return list<float>
     */
    public function wander(int $count, float $sigma, float $persistence = 0.6): array
    {
        $values = [];
        $level = 0.0;
        $shock = $sigma * sqrt(1 - $persistence ** 2);
        for ($i = 0; $i < $count; $i++) {
            $level = $persistence * $level + $shock * $this->normal();
            $values[] = exp($level - $sigma ** 2 / 2);
        }

        return $values;
    }
}
