<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

final class GscConnector extends AbstractConnector
{
    /** Queries kept per reporting window; Search Console returns them by clicks, most first. */
    public const QUERY_LIMIT = 500;

    /** Rows read from the previous window, only to compare the current queries with it. */
    private const PREVIOUS_LIMIT = 2000;

    /** Queries kept per archived month, so a month compared with another still finds most of the same queries. */
    public const MONTH_LIMIT = 1000;

    /** Search Console keeps 16 months of data; older months would be incomplete. */
    public const ARCHIVE_MONTHS = 16;

    /** Months archived per collection, newest first: a new data source fills its history over its first collections. */
    public const MONTHS_PER_COLLECTION = 4;

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

        return new CollectionResult(daily: $daily, queries: $this->queries($site, $headers, $all === [] ? null : max(array_keys($all)), $boundary),
            queryMonths: $this->queryMonths($site, $headers, $boundary, $source->archivedQueryMonths));
    }

    /**
     * Top queries of complete calendar months whose data is final and that are not archived yet.
     *
     * @param  array<string, string>  $headers
     * @param  list<string>  $archived
     * @return list<array{month: string, start_date: string, end_date: string, weeks: int|null, rows: list<array{query: string, clicks: float, impressions: float, position: float|null}>}>
     */
    private function queryMonths(string $site, array $headers, ?string $boundary, array $archived): array
    {
        if ($boundary === null) {
            return [];
        }
        $final = CarbonImmutable::parse($boundary);
        $earliest = $final->subMonthsNoOverflow(self::ARCHIVE_MONTHS);
        $months = [];
        // The newest month that ends on or before the last final date.
        for ($month = $final->addDay()->startOfMonth()->subMonthNoOverflow(); $month->gte($earliest) && count($months) < self::MONTHS_PER_COLLECTION; $month = $month->subMonthNoOverflow()) {
            if (in_array($month->format('Y-m'), $archived, true)) {
                continue;
            }
            $end = $month->endOfMonth()->startOfDay();
            $rows = [];
            foreach ($this->queryRows($site, $headers, $month, $end, self::MONTH_LIMIT) as $text => $row) {
                $rows[] = ['query' => (string) $text] + $row;
            }
            $months[] = ['month' => $month->toDateString(), 'start_date' => $month->toDateString(), 'end_date' => $end->toDateString(), 'weeks' => null, 'rows' => $rows];
        }

        return $months;
    }

    /**
     * Top queries for the 7, 30 and 90 days that end on the latest reported date, plus the same queries in the window before.
     * Search Console leaves out rare (anonymized) queries, so their clicks can add up to less than the site total.
     *
     * @param  array<string, string>  $headers
     * @return list<array{days: int, previous: bool, start_date: string, end_date: string, query: string, clicks: float, impressions: float, position: float|null, is_partial: bool}>
     */
    private function queries(string $site, array $headers, ?string $latest, ?string $boundary): array
    {
        if ($latest === null) {
            return [];
        }
        $end = CarbonImmutable::parse($latest);
        $rows = [];
        foreach ([7, 30, 90] as $days) {
            $start = $end->subDays($days - 1);
            $current = $this->queryRows($site, $headers, $start, $end, self::QUERY_LIMIT);
            $previousEnd = $start->subDay();
            $previous = $current === [] ? [] : array_intersect_key($this->queryRows($site, $headers, $previousEnd->subDays($days - 1), $previousEnd, self::PREVIOUS_LIMIT), $current);
            foreach ([[$current, false, $start, $end], [$previous, true, $previousEnd->subDays($days - 1), $previousEnd]] as [$set, $isPrevious, $from, $to]) {
                foreach ($set as $text => $row) {
                    $rows[] = ['days' => $days, 'previous' => $isPrevious, 'start_date' => $from->toDateString(), 'end_date' => $to->toDateString(), 'query' => (string) $text] + $row
                        + ['is_partial' => $boundary === null || $to->toDateString() > $boundary];
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, array{clicks: float, impressions: float, position: float|null}>
     */
    private function queryRows(string $site, array $headers, CarbonImmutable $start, CarbonImmutable $end, int $limit): array
    {
        $data = $this->json($this->http->request('POST', 'https://searchconsole.googleapis.com/webmasters/v3/sites/'.rawurlencode($site).'/searchAnalytics/query', [
            'headers' => $headers, 'json' => [
                'startDate' => $start->toDateString(), 'endDate' => $end->toDateString(),
                'dimensions' => ['query'], 'rowLimit' => $limit, 'dataState' => 'all',
            ],
        ]), 'Search Console queries');
        $parsed = [];
        foreach ($this->list($data['rows'] ?? [], 'Search Console queries') as $raw) {
            $row = $this->object($raw, 'Search Console queries');
            $keys = $this->list($row['keys'] ?? null, 'Search Console queries');
            $text = $keys[0] ?? null;
            if (count($keys) !== 1 || ! is_string($text) || trim($text) === '' || mb_strlen($text) > 1000 || isset($parsed[$text])) {
                throw new ConnectorException('Search Console returned an invalid or duplicate query.');
            }
            $impressions = $this->number($row['impressions'] ?? null, 'Search Console queries');
            $position = $row['position'] ?? null;
            $parsed[$text] = ['clicks' => $this->number($row['clicks'] ?? null, 'Search Console queries'), 'impressions' => $impressions,
                'position' => $impressions > 0 && (is_int($position) || is_float($position)) && $position > 0 ? (float) $position : null];
        }

        return $parsed;
    }
}
