<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

final class GscConnector extends AbstractConnector
{
    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        if ($source->reportingTimezone !== 'America/Los_Angeles') {
            throw new ConnectorException('Search Console daily dates require America/Los_Angeles reporting timezone.');
        }
        [$start, $today] = $this->window($source, $now, 3);
        $site = $this->text($source->config['site_url'] ?? null, 'Search Console property');
        $token = (new GoogleToken($this->http))->fetch($credentials, 'https://www.googleapis.com/auth/webmasters.readonly');
        $headers = ['Authorization' => 'Bearer '.$token];
        $data = $this->json($this->http->request('GET', 'https://searchconsole.googleapis.com/webmasters/v3/sites', ['headers' => $headers]), 'Search Console property list');
        $visible = false;
        foreach ($this->list($data['siteEntry'] ?? [], 'Search Console') as $raw) {
            $entry = $this->object($raw, 'Search Console');
            if (($entry['siteUrl'] ?? null) === $site && ($entry['permissionLevel'] ?? null) !== 'siteUnverifiedUser') {
                $visible = true;
            }
        }
        if (! $visible) {
            throw new ConnectorException('The Search Console property is not accessible to these credentials.');
        }
        $query = function (string $state) use ($site, $headers, $start, $today): array {
            $data = $this->json($this->http->request('POST', 'https://searchconsole.googleapis.com/webmasters/v3/sites/'.rawurlencode($site).'/searchAnalytics/query', [
                'headers' => $headers, 'json' => [
                    'startDate' => $start->toDateString(), 'endDate' => $today->toDateString(),
                    'dimensions' => ['date'], 'rowLimit' => 25000, 'dataState' => $state,
                ],
            ]), 'Search Console');
            $rows = $this->list($data['rows'] ?? [], 'Search Console');
            if (count($rows) >= 25000) {
                throw new ConnectorException('Search Console reached its row limit; incomplete data was rejected.');
            }
            $parsed = [];
            foreach ($rows as $raw) {
                $row = $this->object($raw, 'Search Console');
                $keys = $this->list($row['keys'] ?? null, 'Search Console');
                $date = $this->date($keys[0] ?? null, 'Search Console');
                if (count($keys) !== 1 || isset($parsed[$date]) || $date < $start->toDateString() || $date > $today->toDateString()) {
                    throw new ConnectorException('Search Console returned a duplicate or out-of-window daily dimension.');
                }
                $parsed[$date] = ['impressions' => $this->number($row['impressions'] ?? null, 'Search Console'),
                    'clicks' => $this->number($row['clicks'] ?? null, 'Search Console')];
            }
            ksort($parsed);

            return $parsed;
        };
        $final = $query('final');
        $all = $query('all');
        $boundary = $final === [] ? null : max(array_keys($final));
        $daily = [];
        foreach ($all as $date => $metrics) {
            foreach ($metrics as $metric => $value) {
                $daily[] = $this->daily($metric, $date, $value, $boundary === null || $date > $boundary);
            }
        }

        return new CollectionResult(daily: $daily);
    }
}
