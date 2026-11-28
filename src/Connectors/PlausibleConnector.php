<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/** Plausible Stats API v2. Daily rows are the site timezone. Period visitors are a separate aggregate. */
final class PlausibleConnector extends AbstractConnector
{
    private const DAILY_LIMIT = 4000;

    /** Rows of a daily breakdown: every day of the window times every channel or goal. */
    private const BREAKDOWN_DAILY_LIMIT = 10000;

    /** Breakdown dimensions and the Plausible dimension that answers each. */
    private const DIMENSIONS = ['referrer' => 'visit:source', 'landing_page' => 'visit:entry_page', 'campaign' => 'visit:utm_campaign',
        'country' => 'visit:country', 'device' => 'visit:device', 'goal' => 'event:goal'];

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $base = $this->base($credentials['base_url'] ?? null);
        $key = $this->text($credentials['api_key'] ?? null, 'Plausible API key');
        $site = $this->site($source->config['site_id'] ?? null);
        [$start, $today] = $this->window($source, $now);
        $client = new PlausibleClient($this->http);
        $website = $this->siteDetails($client, $base, $key, $site);
        $timezone = $this->text($website['timezone'] ?? null, 'Plausible site timezone', 64);
        if (! $this->reportingTimezoneMatches($timezone, $source->reportingTimezone)) {
            throw new ConnectorException('The Plausible site timezone does not match this source reporting timezone.');
        }
        $dailyBody = [
            'site_id' => $site,
            'metrics' => ['visitors', 'visits', 'pageviews', 'bounce_rate', 'visit_duration'],
            'date_range' => [$start->toDateString(), $today->toDateString()],
            'dimensions' => ['time:day'],
            'pagination' => ['limit' => self::DAILY_LIMIT, 'offset' => 0],
        ];
        $rows = $this->results($this->query($client, $base, $key, $dailyBody), 5, [3, 4]);
        if (count($rows) >= self::DAILY_LIMIT) {
            throw new ConnectorException('Plausible returned a truncated daily report.');
        }
        $daily = [];
        $seen = [];
        foreach ($rows as $row) {
            $date = $this->date($row['dimensions'][0] ?? null, 'Plausible');
            if (isset($seen[$date]) || $date < $start->toDateString() || $date > $today->toDateString()) {
                throw new ConnectorException('Plausible returned a duplicate or out-of-window date.');
            }
            $seen[$date] = true;
            [$visitors, $visits, $views, $bounceRate, $duration] = $row['metrics'];
            $values = ['visitors' => $visitors, 'visits' => $visits, 'pageviews' => $views];
            // Plausible reports a day's bounce rate (percent) and average visit duration (seconds); their parts follow from the visits.
            if ($bounceRate !== null && $duration !== null && $bounceRate <= 100) {
                $values += ['bounces' => round($visits * $bounceRate / 100), 'visit_duration' => $visits * $duration];
            }
            foreach ($values as $metric => $value) {
                $daily[] = $this->daily($metric, $date, (float) $value, $date === $today->toDateString());
            }
        }
        $periods = [];
        foreach ($this->periodWindows($source, $today) as [$from, $end]) {
            $body = ['site_id' => $site, 'metrics' => ['visitors'], 'date_range' => [$from->toDateString(), $end->toDateString()]];
            $totals = $this->results($this->query($client, $base, $key, $body), 1);
            if ($totals === []) {
                continue;
            }
            if (count($totals) !== 1 || $totals[0]['dimensions'] !== []) {
                throw new ConnectorException('Plausible returned an unexpected visitor total.');
            }
            $periods[] = ['metric' => 'visitors', 'start_date' => $from->toDateString(), 'end_date' => $end->toDateString(),
                'value' => $totals[0]['metrics'][0], 'is_partial' => $end->equalTo($today)];
        }
        if (($source->config['breakdowns'] ?? true) === false) {
            return new CollectionResult(daily: $daily, periods: $periods);
        }

        $errors = [];
        $range = [$start->toDateString(), $today->toDateString()];
        $byDay = function (string $dimension, string $metric) use ($client, $base, $key, $site, $range, $seen): array {
            $body = ['site_id' => $site, 'metrics' => [$metric], 'date_range' => $range, 'dimensions' => ['time:day', $dimension], 'pagination' => ['limit' => self::BREAKDOWN_DAILY_LIMIT, 'offset' => 0]];
            $rows = $this->results($this->query($client, $base, $key, $body), 1);
            if (count($rows) >= self::BREAKDOWN_DAILY_LIMIT) {
                throw new ConnectorException('Plausible returned a truncated daily report.');
            }

            return array_map(function (array $row) use ($seen): array {
                $date = $this->date($row['dimensions'][0] ?? null, 'Plausible');
                $item = $row['dimensions'][1] ?? null;
                if (! isset($seen[$date]) || ! is_string($item)) {
                    throw new ConnectorException('Plausible returned an unexpected daily breakdown.');
                }

                return ['date' => $date, 'item' => $item, 'value' => $row['metrics'][0]];
            }, $rows);
        };
        $channelDays = null;
        try {
            $channelDays = Breakdowns::channelDays(array_map(fn (array $row): array => ['date' => $row['date'], 'channel' => $row['item'], 'visits' => $row['value']], $byDay('visit:channel', 'visits')), array_keys($seen), $today);
        } catch (ConnectorException $exception) {
            $errors['channel_days'] = $exception->getMessage();
        }
        try {
            // Goal completions per day. A site without goals, or without completions in the window, records no conversions.
            $completions = [];
            foreach ($byDay('event:goal', 'events') as $row) {
                $completions[$row['date']] = ($completions[$row['date']] ?? 0.0) + $row['value'];
            }
            if (array_sum($completions) > 0) {
                foreach (array_keys($seen) as $date) {
                    $daily[] = $this->daily('conversions', $date, $completions[$date] ?? 0.0, $date === $today->toDateString());
                }
            }
        } catch (ConnectorException $exception) {
            $errors['conversions'] = $exception->getMessage();
        }
        $fetchers = [];
        foreach (self::DIMENSIONS as $dimension => $name) {
            $metrics = $dimension === 'goal' ? ['events', 'visitors'] : ['visits', 'visitors'];
            $fetchers[$dimension] = Breakdowns::perWindow(function (CarbonImmutable $from, CarbonImmutable $end) use ($client, $base, $key, $site, $dimension, $name, $metrics): array {
                $body = ['site_id' => $site, 'metrics' => $metrics, 'date_range' => [$from->toDateString(), $end->toDateString()], 'dimensions' => [$name],
                    'order_by' => [[$metrics[0], 'desc']], 'pagination' => ['limit' => Breakdowns::LIMIT, 'offset' => 0]];
                $items = [];
                foreach ($this->results($this->query($client, $base, $key, $body), 2) as $row) {
                    $item = $row['dimensions'][0] ?? null;
                    if (! is_string($item) || ($dimension === 'referrer' && $item === 'Direct / None')) {
                        continue;
                    }
                    $items[] = $dimension === 'goal' ? ['item' => $item, 'conversions' => $row['metrics'][0], 'visitors' => $row['metrics'][1]]
                        : ['item' => $item, 'visits' => $row['metrics'][0], 'visitors' => $row['metrics'][1]];
                }

                return $items;
            });
        }
        $breakdowns = Breakdowns::collect($source->breakdownLists ? $fetchers : [], $today);
        $months = Breakdowns::months($source->breakdownLists ? $fetchers : [], $source->breakdownMonths, $today);

        return new CollectionResult(daily: $daily, periods: $periods, channelDays: $channelDays, breakdowns: $breakdowns['rows'],
            breakdownDimensions: $breakdowns['dimensions'], breakdownErrors: $errors + $breakdowns['errors'] + $months['errors'], breakdownMonths: $months['months']);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function query(PlausibleClient $client, string $base, string $key, array $body): array
    {
        $data = $this->decoded($client->request('POST', $base, $key, '/api/v2/query', ['json' => $body]));
        $query = $this->object($data['query'] ?? null, 'Plausible');
        if (($query['site_id'] ?? null) !== $body['site_id'] || ($query['metrics'] ?? null) !== $body['metrics']) {
            throw new ConnectorException('Plausible returned an unexpected query.');
        }
        $warnings = $this->object($data['meta'] ?? [], 'Plausible')['metric_warnings'] ?? [];
        if (! is_array($warnings) || $warnings !== []) {
            throw new ConnectorException('Plausible could not calculate a requested metric.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $nullable  Metric positions that may be null (Plausible leaves a rate empty when it cannot be calculated).
     * @return list<array{dimensions: list<mixed>, metrics: list<float|null>}>
     */
    private function results(array $data, int $metrics, array $nullable = []): array
    {
        $rows = [];
        foreach ($this->list($data['results'] ?? null, 'Plausible') as $raw) {
            $row = $this->object($raw, 'Plausible');
            $values = $this->list($row['metrics'] ?? null, 'Plausible');
            $dimensions = $this->list($row['dimensions'] ?? [], 'Plausible');
            if (count($values) !== $metrics) {
                throw new ConnectorException('Plausible returned an unexpected metric count.');
            }
            $rows[] = ['dimensions' => $dimensions, 'metrics' => array_map(fn (mixed $value, int $index): ?float => $value === null && in_array($index, $nullable, true) ? null : $this->number($value, 'Plausible'), $values, array_keys($values))];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function siteDetails(PlausibleClient $client, string $base, string $key, string $site): array
    {
        $response = $client->request('GET', $base, $key, '/api/v1/sites/'.rawurlencode($site));
        if ($response->status() === 404) {
            throw new ConnectorException('The Plausible site is not visible to this API key.');
        }

        $data = $this->decoded($response);
        if (strcasecmp((string) ($data['domain'] ?? ''), $site) !== 0) {
            throw new ConnectorException('Plausible returned a different site.');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function decoded(Response $response): array
    {
        if ($response->status() !== 200) {
            throw new ConnectorException('Plausible returned HTTP '.$response->status().'. Check the API key, site domain and server URL.');
        }

        return $this->json($response, 'Plausible');
    }

    private function base(mixed $value): string
    {
        $base = rtrim($this->text($value, 'Plausible server URL', 500), '/');
        $parts = parse_url($base);
        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || ($parts['port'] ?? 443) !== 443 || str_contains($parts['path'] ?? '', '..')) {
            throw new ConnectorException('Use a public HTTPS Plausible server URL without a query or fragment.');
        }

        return $base;
    }

    private function site(mixed $value): string
    {
        $site = strtolower($this->text($value, 'Plausible site domain', 253));
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/D', $site)) {
            throw new ConnectorException('Use the Plausible site domain, such as example.com.');
        }

        return $site;
    }
}
