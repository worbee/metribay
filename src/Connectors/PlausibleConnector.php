<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/** Plausible Stats API v2. Daily rows are the site timezone. Period visitors are a separate aggregate. */
final class PlausibleConnector extends AbstractConnector
{
    private const DAILY_LIMIT = 4000;

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
            'metrics' => ['visitors', 'visits', 'pageviews'],
            'date_range' => [$start->toDateString(), $today->toDateString()],
            'dimensions' => ['time:day'],
            'pagination' => ['limit' => self::DAILY_LIMIT, 'offset' => 0],
        ];
        $rows = $this->results($this->query($client, $base, $key, $dailyBody), 3);
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
            foreach (['visitors', 'visits', 'pageviews'] as $index => $metric) {
                $daily[] = $this->daily($metric, $date, $row['metrics'][$index], $date === $today->toDateString());
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

        return new CollectionResult(daily: $daily, periods: $periods);
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
     * @return list<array{dimensions: list<mixed>, metrics: list<float>}>
     */
    private function results(array $data, int $metrics): array
    {
        $rows = [];
        foreach ($this->list($data['results'] ?? null, 'Plausible') as $raw) {
            $row = $this->object($raw, 'Plausible');
            $values = $this->list($row['metrics'] ?? null, 'Plausible');
            $dimensions = $this->list($row['dimensions'] ?? [], 'Plausible');
            if (count($values) !== $metrics) {
                throw new ConnectorException('Plausible returned an unexpected metric count.');
            }
            $rows[] = ['dimensions' => $dimensions, 'metrics' => array_map(fn (mixed $value): float => $this->number($value, 'Plausible'), $values)];
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
