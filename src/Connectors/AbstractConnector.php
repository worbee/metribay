<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

abstract class AbstractConnector implements Connector
{
    public function __construct(protected readonly SafeHttpClient $http) {}

    /** @return array<string, mixed> */
    protected function object(mixed $value, string $provider): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new ConnectorException("{$provider} returned an invalid object.");
        }

        return $value;
    }

    /** @return list<mixed> */
    protected function list(mixed $value, string $provider): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new ConnectorException("{$provider} returned an invalid list.");
        }

        return $value;
    }

    /** @return array<string, mixed> */
    protected function json(Response $response, string $provider, int $status = 200): array
    {
        if ($response->status() !== $status) {
            throw new ConnectorException("{$provider} returned HTTP {$response->status()}. Check access, configuration, and provider limits.");
        }

        try {
            $data = json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new ConnectorException("{$provider} returned invalid JSON.");
        }

        return $this->object($data, $provider);
    }

    protected function text(mixed $value, string $field, int $maxLength = 4096): string
    {
        if (! is_string($value) || trim($value) === '' || strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new ConnectorException("A valid {$field} is required.");
        }

        return trim($value);
    }

    protected function number(mixed $value, string $provider, ?float $maximum = null): float
    {
        if ((! is_int($value) && ! is_float($value) && ! (is_string($value) && preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $value)))
            || ! is_finite((float) $value) || (float) $value < 0 || ($maximum !== null && (float) $value > $maximum)) {
            throw new ConnectorException("{$provider} returned an invalid metric value.");
        }

        return (float) $value;
    }

    protected function count(mixed $value, string $provider): int
    {
        $number = $this->number($value, $provider, 9_007_199_254_740_991.0);
        if (floor($number) !== $number) {
            throw new ConnectorException("{$provider} returned an invalid count.");
        }

        return (int) $number;
    }

    protected function date(mixed $value, string $provider): string
    {
        if (! is_string($value) || ! preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new ConnectorException("{$provider} returned an invalid date.");
        }

        return $value;
    }

    protected function timestamp(mixed $value, string $provider): CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d+)?(?:Z|[+-]\d\d:\d\d)$/D', $value)) {
            throw new ConnectorException("{$provider} returned an invalid timestamp.");
        }
        $this->date(substr($value, 0, 10), $provider);
        if ((int) substr($value, 11, 2) > 23 || (int) substr($value, 14, 2) > 59 || (int) substr($value, 17, 2) > 59
            || (! str_ends_with($value, 'Z') && ((int) substr($value, -5, 2) > 23 || (int) substr($value, -2) > 59))) {
            throw new ConnectorException("{$provider} returned an invalid timestamp.");
        }
        try {
            $parsed = new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) {
                throw new \InvalidArgumentException;
            }

            return CarbonImmutable::instance($parsed)->utc();
        } catch (\Throwable) {
            throw new ConnectorException("{$provider} returned an invalid timestamp.");
        }
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    protected function window(SourceConfiguration $source, CarbonImmutable $now, int $minimumDays = 1, int $defaultDays = 90): array
    {
        $days = $source->config['days'] ?? $defaultDays;
        if (! is_int($days) || $days < 1 || $days > 3660) {
            throw new ConnectorException('days must be an integer between 1 and 3660.');
        }
        try {
            $today = $now->setTimezone($source->reportingTimezone)->startOfDay();
        } catch (\Throwable) {
            throw new ConnectorException('A valid reporting timezone is required.');
        }
        $start = $source->from?->setTimezone($source->reportingTimezone)->startOfDay()
            ?? $today->subDays(max($minimumDays, $days) - 1);
        $start = $start->min($today->subDays($minimumDays - 1));
        if ($start->diffInDays($today) > 3660) {
            throw new ConnectorException('The requested collection window is too large.');
        }

        return [$start, $today];
    }

    /** @return list<array{CarbonImmutable, CarbonImmutable}> */
    protected function periodWindows(SourceConfiguration $source, CarbonImmutable $today): array
    {
        $lengths = $source->config['period_days'] ?? [7, 30, 90];
        if (! is_array($lengths) || ! array_is_list($lengths) || count($lengths) > 12) {
            throw new ConnectorException('period_days must be a short list of day counts.');
        }
        $windows = [];
        foreach ($lengths as $length) {
            if (! is_int($length) || $length < 1 || $length > 3660) {
                throw new ConnectorException('Each period must contain between 1 and 3660 days.');
            }
        }
        foreach (array_unique($lengths) as $length) {
            foreach ([0, $length] as $offset) {
                $end = $today->subDays($offset);
                $windows[] = [$end->subDays($length - 1), $end];
            }
        }

        return $windows;
    }

    protected function utcOnly(SourceConfiguration $source, string $provider): void
    {
        if (! in_array($source->reportingTimezone, ['UTC', 'Etc/UTC', 'Etc/GMT', 'GMT'], true)) {
            throw new ConnectorException("{$provider} sources require UTC reporting timezone.");
        }
    }

    /** UTC aliases name the same offset. Any other pair must match exactly. */
    protected function reportingTimezoneMatches(string $actual, string $expected): bool
    {
        if ($actual === $expected) {
            return true;
        }
        $utc = ['UTC', 'Etc/UTC', 'Etc/GMT', 'GMT'];

        return in_array($actual, $utc, true) && in_array($expected, $utc, true);
    }

    /** @return array{metric: string, date: string, value: float, is_partial: bool} */
    protected function daily(string $metric, string $date, float $value, bool $partial = false): array
    {
        return ['metric' => $metric, 'date' => $date, 'value' => $value, 'is_partial' => $partial];
    }
}
