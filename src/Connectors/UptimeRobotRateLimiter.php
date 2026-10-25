<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

/** UptimeRobot free accounts: at most ten requests/minute, shared across sources and workers. */
final class UptimeRobotRateLimiter
{
    private readonly Closure $clock;

    private readonly Closure $sleep;

    public function __construct(?callable $clock = null, ?callable $sleep = null)
    {
        $this->clock = $clock === null ? static fn (): float => microtime(true) : Closure::fromCallable($clock);
        $this->sleep = $sleep === null ? static fn (int $microseconds) => Sleep::usleep($microseconds) : Closure::fromCallable($sleep);
    }

    public function wait(string $accountToken): void
    {
        $key = 'metribay:uptimerobot:'.hash('sha256', $accountToken);
        try {
            Cache::lock($key.':lock', 15)->block(30, function () use ($key): void {
                $now = (float) ($this->clock)();
                $next = (float) Cache::get($key, 0);
                $delay = max(0.0, $next - $now);
                if ($delay > 6.2) {
                    throw new ConnectorException('The UptimeRobot request limiter is temporarily unavailable.');
                }
                if ($delay > 0) {
                    ($this->sleep)((int) ceil($delay * 1_000_000));
                }
                $sentAt = max($now + $delay, (float) ($this->clock)());
                Cache::put($key, $sentAt + 6.1, 120);
            });
        } catch (ConnectorException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new ConnectorException('The UptimeRobot request limiter is busy. Retry collection.');
        }
    }
}
