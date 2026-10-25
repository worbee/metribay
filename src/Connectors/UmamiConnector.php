<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/** Umami sessions time-series means visitors; actual visits come from /stats. */
final class UmamiConnector extends AbstractConnector
{
    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'Umami');
        [$start, $today] = $this->window($source, $now);
        $periodWindows = $this->periodWindows($source, $today);
        $base = rtrim($this->text($source->config['base_url'] ?? $credentials['base_url'] ?? null, 'Umami base URL'), '/');
        $parts = parse_url($base);
        if ($parts === false || isset($parts['query']) || isset($parts['fragment'])) {
            throw new ConnectorException('Use an Umami HTTPS base URL without query or fragment.');
        }
        $id = $this->text($source->config['website_id'] ?? null, 'Umami website ID', 128);
        if (! preg_match('/^[A-Za-z0-9_-]+$/D', $id)) {
            throw new ConnectorException('Use a valid Umami website ID.');
        }
        $token = isset($credentials['token']) ? $this->text($credentials['token'], 'Umami token') : null;
        $login = function () use ($credentials, $base): string {
            $password = $credentials['password'] ?? null;
            if (! is_string($password) || $password === '' || strlen($password) > 4096) {
                throw new ConnectorException('A valid Umami password is required.');
            }
            $data = $this->json($this->http->request('POST', $base.'/api/auth/login', ['json' => [
                'username' => $this->text($credentials['username'] ?? null, 'Umami username'),
                'password' => $password,
            ]]), 'Umami authentication');

            return $this->text($data['token'] ?? null, 'Umami access token');
        };
        $get = function (string $path, array $query = []) use (&$token, $login, $credentials, $base): mixed {
            $token ??= $login();
            $response = $this->http->request('GET', $base.$path, ['headers' => ['Authorization' => 'Bearer '.$token], 'query' => $query]);
            if ($response->status() === 401 && isset($credentials['username'], $credentials['password'])) {
                $token = $login();
                $response = $this->http->request('GET', $base.$path, ['headers' => ['Authorization' => 'Bearer '.$token], 'query' => $query]);
            }
            if ($response->status() !== 200) {
                throw new ConnectorException('Umami returned HTTP '.$response->status().'. Check authentication and website access.');
            }
            try {
                return json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                throw new ConnectorException('Umami returned invalid JSON.');
            }
        };
        $path = '/api/websites/'.$id;
        // Invalid IDs return plausible empty traffic, so verify visibility independently.
        $website = $get($path);
        if ($website === null) {
            throw new ConnectorException('The Umami website is not visible to these credentials.');
        }
        $website = $this->object($website, 'Umami');
        if (isset($website['id']) && $website['id'] !== $id) {
            throw new ConnectorException('Umami returned a different website.');
        }
        $created = null;
        if (isset($website['createdAt'])) {
            try {
                $created = $this->timestamp($website['createdAt'], 'Umami')->startOfDay();
            } catch (ConnectorException) {
                // Unknown creation dates cannot justify writing omitted quiet days.
            }
        }
        $query = fn (CarbonImmutable $from, CarbonImmutable $end): array => [
            'startAt' => $from->getTimestampMs(), 'endAt' => $end->addDay()->getTimestampMs(),
            'unit' => 'day', 'timezone' => 'UTC',
        ];
        $data = $this->object($get($path.'/pageviews', $query($start, $today)), 'Umami');
        $dates = [];
        foreach (['pageviews' => 'pageviews', 'sessions' => 'visitors'] as $field => $metric) {
            $seen = [];
            foreach ($this->list($data[$field] ?? null, 'Umami') as $raw) {
                $row = $this->object($raw, 'Umami');
                $stamp = $this->text($row['x'] ?? null, 'Umami day');
                $date = $this->date(substr($stamp, 0, 10), 'Umami');
                $count = $this->number($row['y'] ?? null, 'Umami');
                if (isset($seen[$date]) || $date > $today->toDateString()) {
                    throw new ConnectorException('Umami returned a duplicate or future date.');
                }
                $seen[$date] = true;
                // Some self-hosted versions leak a truncated bucket preceding startAt.
                if ($date >= $start->toDateString()) {
                    $dates[$date][$metric] = $count;
                }
            }
        }
        ksort($dates);
        foreach ($dates as $date => &$values) {
            $day = CarbonImmutable::parse($date, 'UTC');
            $stats = $this->object($get($path.'/stats', $query($day, $day)), 'Umami');
            $values['visits'] = $this->stat($stats, 'visits');
        }
        unset($values);
        if ($created !== null) {
            for ($day = $start->max($created->addDay()); $day < $today; $day = $day->addDay()) {
                $date = $day->toDateString();
                if (isset($dates[$date])) {
                    continue;
                }
                $stats = $this->object($get($path.'/stats', $query($day, $day)), 'Umami');
                try {
                    $counts = [];
                    foreach (['pageviews', 'visitors', 'visits'] as $metric) {
                        $counts[$metric] = $this->stat($stats, $metric);
                    }
                    $dates[$date] = $counts;
                } catch (ConnectorException) {
                    // An ambiguous omitted day remains unknown, never a fabricated zero.
                }
            }
        }
        $daily = [];
        ksort($dates);
        foreach ($dates as $date => $values) {
            foreach ($values as $metric => $value) {
                $daily[] = $this->daily($metric, $date, $value, $date === $today->toDateString());
            }
        }
        $periods = [];
        foreach ($periodWindows as [$from, $end]) {
            $stats = $this->object($get($path.'/stats', $query($from, $end)), 'Umami');
            $periods[] = ['metric' => 'visitors', 'start_date' => $from->toDateString(), 'end_date' => $end->toDateString(),
                'value' => $this->stat($stats, 'visitors'), 'is_partial' => $end->equalTo($today)];
        }

        return new CollectionResult(daily: $daily, periods: $periods);
    }

    /** @param array<string, mixed> $stats */
    private function stat(array $stats, string $metric): float
    {
        $value = $stats[$metric] ?? null;
        // Umami versions expose either a count or {value, prev} for stats.
        if (is_array($value)) {
            $value = $value['value'] ?? null;
        }

        return $this->number($value, 'Umami');
    }
}
