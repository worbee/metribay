<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/**
 * Matomo Reporting API. Visits and visitors come from VisitsSummary; page views from Actions.
 * A custom range does not include true unique visitors, so period visitor totals stay unknown.
 */
final class MatomoConnector extends AbstractConnector
{
    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $base = $this->base($credentials['base_url'] ?? null);
        $token = $this->text($credentials['token_auth'] ?? null, 'Matomo auth token');
        $id = $this->site($source->config['site_id'] ?? null);
        [$start, $today] = $this->window($source, $now);
        $client = new MatomoClient($this->http);
        $website = $this->object($this->call($client, $base, $token, ['method' => 'SitesManager.getSiteFromId', 'idSite' => $id]), 'Matomo');
        if ((string) ($website['idsite'] ?? '') !== $id) {
            throw new ConnectorException('The Matomo site is not visible to this auth token.');
        }
        $timezone = $this->text($website['timezone'] ?? null, 'Matomo site timezone', 64);
        if (! $this->reportingTimezoneMatches($timezone, $source->reportingTimezone)) {
            throw new ConnectorException('The Matomo site timezone does not match this source reporting timezone.');
        }
        $date = $start->toDateString().','.$today->toDateString();
        $summary = $this->days($this->call($client, $base, $token, ['method' => 'VisitsSummary.get', 'idSite' => $id, 'period' => 'day', 'date' => $date]));
        $actions = $this->days($this->call($client, $base, $token, ['method' => 'Actions.get', 'idSite' => $id, 'period' => 'day', 'date' => $date]));
        $dates = array_unique([...array_keys($summary), ...array_keys($actions)]);
        sort($dates);
        $daily = [];
        foreach ($dates as $dateKey) {
            $day = $this->date($dateKey, 'Matomo');
            if ($day < $start->toDateString() || $day > $today->toDateString() || ! array_key_exists($day, $summary) || ! array_key_exists($day, $actions)) {
                throw new ConnectorException('Matomo returned an inconsistent or out-of-window day.');
            }
            $visits = $summary[$day];
            $views = $actions[$day];
            if ($visits === [] && $views === []) {
                $values = ['visitors' => 0.0, 'visits' => 0.0, 'pageviews' => 0.0];
            } elseif (is_array($visits) && ! array_is_list($visits) && is_array($views) && ! array_is_list($views)) {
                $values = [
                    'visitors' => $this->number($visits['nb_uniq_visitors'] ?? null, 'Matomo'),
                    'visits' => $this->number($visits['nb_visits'] ?? null, 'Matomo'),
                    'pageviews' => $this->number($views['nb_pageviews'] ?? null, 'Matomo'),
                ];
            } else {
                throw new ConnectorException('Matomo returned an inconsistent daily report.');
            }
            foreach ($values as $metric => $value) {
                $daily[] = $this->daily($metric, $day, $value, $day === $today->toDateString());
            }
        }

        return new CollectionResult(daily: $daily);
    }

    /**
     * @param  array<string, string>  $params
     * @return array<mixed>
     */
    private function call(MatomoClient $client, string $base, string $token, array $params): array
    {
        $response = $client->call($base, $token, $params);
        if ($response->status() !== 200) {
            throw new ConnectorException('Matomo returned HTTP '.$response->status().'. Check the auth token, site ID and server URL.');
        }
        try {
            $data = json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new ConnectorException('Matomo returned invalid JSON.');
        }
        if (! is_array($data) || ($data['result'] ?? null) === 'error') {
            throw new ConnectorException('Matomo rejected the request. Check the auth token, site ID and server URL.');
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    private function days(array $data): array
    {
        if (array_is_list($data) || ($data['result'] ?? null) === 'error') {
            throw new ConnectorException('Matomo returned an invalid daily report.');
        }

        return $data;
    }

    private function base(mixed $value): string
    {
        $base = rtrim($this->text($value, 'Matomo server URL', 500), '/');
        $parts = parse_url($base);
        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || ($parts['port'] ?? 443) !== 443 || str_contains($parts['path'] ?? '', '..')) {
            throw new ConnectorException('Use a public HTTPS Matomo server URL without a query or fragment.');
        }

        return $base;
    }

    private function site(mixed $value): string
    {
        $id = $this->text($value, 'Matomo site ID', 10);
        if (! preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
            throw new ConnectorException('Use the numeric Matomo site ID.');
        }

        return $id;
    }
}
