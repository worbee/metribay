<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

final class BingConnector extends AbstractConnector
{
    /** Bing reports queries by week: 7, 30 and 90 day pages read the latest 1, 4 and 13 weeks. */
    public const QUERY_WEEKS = [7 => 1, 30 => 4, 90 => 13];

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
                $date = $this->dotNetDate($row['Date'] ?? null);
                if ($date > $now->utc()->toDateString() || isset($seen[$date])) {
                    throw new ConnectorException('Bing returned a duplicate or future date.');
                }
                $seen[$date] = true;
                foreach ($metrics as $field => $metric) {
                    $daily[] = $this->daily($metric, $date, (float) $this->count($row[$field] ?? null, 'Bing'), $date === $now->utc()->toDateString());
                }
            }
        }

        return new CollectionResult(daily: $daily, queries: $this->queries($site, $key));
    }

    /** The .NET suffix describes the source offset; epoch milliseconds are absolute UTC. */
    private function dotNetDate(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('~^/Date\((-?[0-9]{1,15})(?:[+-][0-9]{4})?\)/$~D', $value, $parts)) {
            throw new ConnectorException('Bing returned an invalid daily timestamp.');
        }

        return CarbonImmutable::createFromTimestampUTC((float) $parts[1] / 1000)->toDateString();
    }

    /**
     * Weekly query rows summed into windows that end on the latest reported week. Positions are weighted by impressions;
     * Bing reports -1 when there is no average, which stays unknown.
     *
     * @return list<array{days: int, previous: bool, start_date: string, end_date: string, query: string, clicks: float, impressions: float, position: float|null, is_partial: bool}>
     */
    private function queries(string $site, string $key): array
    {
        $data = $this->json($this->http->request('GET', 'https://ssl.bing.com/webmaster/api.svc/json/GetQueryStats', [
            'query' => ['siteUrl' => $site, 'apikey' => $key],
        ]), 'Bing queries');
        $weeks = [];
        foreach ($this->list($data['d'] ?? null, 'Bing queries') as $raw) {
            $row = $this->object($raw, 'Bing queries');
            $text = $row['Query'] ?? null;
            if (! is_string($text) || trim($text) === '' || mb_strlen($text) > 1000) {
                throw new ConnectorException('Bing returned an invalid query.');
            }
            $position = $row['AvgImpressionPosition'] ?? null;
            $weeks[] = ['date' => $this->dotNetDate($row['Date'] ?? null), 'query' => $text, 'clicks' => (float) $this->count($row['Clicks'] ?? null, 'Bing queries'),
                'impressions' => (float) $this->count($row['Impressions'] ?? null, 'Bing queries'), 'position' => (is_int($position) || is_float($position)) && $position > 0 ? (float) $position : null];
        }
        if ($weeks === []) {
            return [];
        }
        $end = CarbonImmutable::parse(max(array_column($weeks, 'date')));
        $rows = [];
        foreach (self::QUERY_WEEKS as $days => $count) {
            $start = $end->subDays(7 * $count - 1);
            $previousEnd = $start->subDay();
            $current = $this->sum($weeks, $start, $end);
            $previous = array_intersect_key($this->sum($weeks, $previousEnd->subDays(7 * $count - 1), $previousEnd), $current);
            foreach ([[$current, false, $start, $end], [$previous, true, $previousEnd->subDays(7 * $count - 1), $previousEnd]] as [$set, $isPrevious, $from, $to]) {
                foreach ($set as $text => $row) {
                    $rows[] = ['days' => $days, 'previous' => $isPrevious, 'start_date' => $from->toDateString(), 'end_date' => $to->toDateString(), 'query' => (string) $text] + $row + ['is_partial' => false];
                }
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{date: string, query: string, clicks: float, impressions: float, position: float|null}>  $weeks
     * @return array<string, array{clicks: float, impressions: float, position: float|null}>
     */
    private function sum(array $weeks, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = [];
        foreach ($weeks as $week) {
            if ($week['date'] < $from->toDateString() || $week['date'] > $to->toDateString()) {
                continue;
            }
            $total = $totals[$week['query']] ?? ['clicks' => 0.0, 'impressions' => 0.0, 'weighted' => 0.0, 'weight' => 0.0];
            $total['clicks'] += $week['clicks'];
            $total['impressions'] += $week['impressions'];
            if ($week['position'] !== null && $week['impressions'] > 0) {
                $total['weighted'] += $week['position'] * $week['impressions'];
                $total['weight'] += $week['impressions'];
            }
            $totals[$week['query']] = $total;
        }
        uasort($totals, fn (array $a, array $b): int => [$b['clicks'], $b['impressions']] <=> [$a['clicks'], $a['impressions']]);

        return array_map(fn (array $total): array => ['clicks' => $total['clicks'], 'impressions' => $total['impressions'],
            'position' => $total['weight'] > 0 ? $total['weighted'] / $total['weight'] : null], array_slice($totals, 0, GscConnector::QUERY_LIMIT, true));
    }
}
