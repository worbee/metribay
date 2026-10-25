<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

final class BingConnector extends AbstractConnector
{
    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'Bing');
        $site = $this->text($source->config['site_url'] ?? null, 'Bing site URL');
        $key = $this->text($credentials['api_key'] ?? null, 'Bing API key');
        $daily = [];
        foreach (['GetRankAndTrafficStats' => ['Impressions' => 'impressions', 'Clicks' => 'clicks'],
            'GetCrawlStats' => ['InIndex' => 'indexed_pages']] as $method => $metrics) {
            // Bing offers no date-range arguments. Fetch its full retention on every collection.
            $response = $this->http->request('GET', 'https://ssl.bing.com/webmaster/api.svc/json/'.$method, [
                'query' => ['siteUrl' => $site, 'apikey' => $key],
            ]);
            if ($response->status() === 400 && $response->json('ErrorCode') === 14) {
                throw new ConnectorException('The Bing API key does not have access to this site.');
            }
            $data = $this->json($response, 'Bing');
            $seen = [];
            foreach ($this->list($data['d'] ?? null, 'Bing') as $raw) {
                $row = $this->object($raw, 'Bing');
                if (! is_string($row['Date'] ?? null) || ! preg_match('~^/Date\((-?[0-9]{1,15})(?:[+-][0-9]{4})?\)/$~D', $row['Date'], $parts)) {
                    throw new ConnectorException('Bing returned an invalid daily timestamp.');
                }
                // The .NET suffix describes the source offset; epoch milliseconds are absolute UTC.
                $date = CarbonImmutable::createFromTimestampUTC((float) $parts[1] / 1000)->toDateString();
                if ($date > $now->utc()->toDateString() || isset($seen[$date])) {
                    throw new ConnectorException('Bing returned a duplicate or future date.');
                }
                $seen[$date] = true;
                foreach ($metrics as $field => $metric) {
                    $daily[] = $this->daily($metric, $date, (float) $this->count($row[$field] ?? null, 'Bing'), $date === $now->utc()->toDateString());
                }
            }
        }

        return new CollectionResult(daily: $daily);
    }
}
