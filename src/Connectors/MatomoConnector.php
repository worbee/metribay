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
    /** Goals read for the goals list; each needs one request. */
    private const MAX_GOALS = 25;

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
                $values = ['visitors' => 0.0, 'visits' => 0.0, 'pageviews' => 0.0, 'bounces' => 0.0, 'visit_duration' => 0.0];
            } elseif (is_array($visits) && ! array_is_list($visits) && is_array($views) && ! array_is_list($views)) {
                $values = [
                    'visitors' => $this->number($visits['nb_uniq_visitors'] ?? null, 'Matomo'),
                    'visits' => $this->number($visits['nb_visits'] ?? null, 'Matomo'),
                    'pageviews' => $this->number($views['nb_pageviews'] ?? null, 'Matomo'),
                ];
                if (isset($visits['bounce_count'], $visits['sum_visit_length'])) {
                    $values += ['bounces' => $this->number($visits['bounce_count'], 'Matomo'), 'visit_duration' => $this->number($visits['sum_visit_length'], 'Matomo')];
                }
            } else {
                throw new ConnectorException('Matomo returned an inconsistent daily report.');
            }
            foreach ($values as $metric => $value) {
                $daily[] = $this->daily($metric, $day, $value, $day === $today->toDateString());
            }
        }

        if (($source->config['breakdowns'] ?? true) === false) {
            return new CollectionResult(daily: $daily);
        }
        $covered = array_values(array_unique(array_column($daily, 'date')));
        $request = fn (array $params): array => $this->call($client, $base, $token, ['idSite' => $id] + $params);
        $errors = [];
        $channelDays = null;
        try {
            $channels = [];
            foreach ($this->days($request(['method' => 'Referrers.getReferrerType', 'period' => 'day', 'date' => $date])) as $day => $rows) {
                if (! in_array($day, $covered, true) || ! is_array($rows) || ($rows !== [] && ! array_is_list($rows))) {
                    throw new ConnectorException('Matomo returned an inconsistent channel report.');
                }
                foreach ($rows as $row) {
                    $row = $this->object($row, 'Matomo');
                    $channels[] = ['date' => $day, 'channel' => $this->referrerType($row), 'visits' => $this->number($row['nb_visits'] ?? null, 'Matomo')];
                }
            }
            $channelDays = Breakdowns::channelDays($channels, $covered, $today);
        } catch (ConnectorException $exception) {
            $errors['channel_days'] = $exception->getMessage();
        }
        $goals = null;
        try {
            $goals = [];
            // Goals.getGoals is keyed by goal ID in current versions and a list in older ones.
            foreach ($request(['method' => 'Goals.getGoals']) as $goal) {
                $goal = $this->object($goal, 'Matomo');
                $goals[(string) ($goal['idgoal'] ?? '')] = $this->text($goal['name'] ?? null, 'Matomo goal name', 1000);
            }
            unset($goals['']);
            $goals = array_slice($goals, 0, self::MAX_GOALS, true);
            if ($goals !== []) {
                // Conversions of all goals per day. Without completions in the window the source records no conversions.
                $completions = [];
                foreach ($this->days($request(['method' => 'Goals.get', 'period' => 'day', 'date' => $date])) as $day => $row) {
                    if (! in_array($day, $covered, true)) {
                        throw new ConnectorException('Matomo returned an inconsistent goals report.');
                    }
                    $completions[$day] = $row === [] ? 0.0 : $this->number($this->object($row, 'Matomo')['nb_conversions'] ?? null, 'Matomo');
                }
                if (array_sum($completions) > 0) {
                    foreach ($covered as $day) {
                        $daily[] = $this->daily('conversions', $day, $completions[$day] ?? 0.0, $day === $today->toDateString());
                    }
                }
            }
        } catch (ConnectorException $exception) {
            $goals = null;
            $errors['conversions'] = $exception->getMessage();
        }
        $range = fn (CarbonImmutable $from, CarbonImmutable $end): array => ['period' => 'range', 'date' => $from->toDateString().','.$end->toDateString(), 'filter_limit' => (string) Breakdowns::LIMIT];
        $rows = fn (array $params): array => array_map(fn (mixed $row): array => $this->object($row, 'Matomo'), $this->list($request($params), 'Matomo'));
        $fetchers = [
            'referrer' => Breakdowns::perWindow(fn (CarbonImmutable $from, CarbonImmutable $end): array => array_values(array_filter(array_map(
                fn (array $row): ?array => $this->referrerType($row) === 'direct' ? null : ['item' => (string) ($row['label'] ?? ''), 'visits' => $this->number($row['nb_visits'] ?? null, 'Matomo')],
                $rows(['method' => 'Referrers.getAll'] + $range($from, $end)))))),
            'landing_page' => Breakdowns::perWindow(fn (CarbonImmutable $from, CarbonImmutable $end): array => array_map(
                fn (array $row): array => ['item' => $this->pagePath($row), 'visits' => $this->number($row['entry_nb_visits'] ?? 0, 'Matomo')],
                $rows(['method' => 'Actions.getEntryPageUrls', 'flat' => '1'] + $range($from, $end)))),
            'campaign' => Breakdowns::perWindow(fn (CarbonImmutable $from, CarbonImmutable $end): array => array_map(
                fn (array $row): array => ['item' => (string) ($row['label'] ?? ''), 'visits' => $this->number($row['nb_visits'] ?? null, 'Matomo')],
                $rows(['method' => 'Referrers.getCampaigns'] + $range($from, $end)))),
            'country' => Breakdowns::perWindow(fn (CarbonImmutable $from, CarbonImmutable $end): array => array_map(
                fn (array $row): array => ['item' => (string) ($row['code'] ?? ''), 'visits' => $this->number($row['nb_visits'] ?? null, 'Matomo')],
                $rows(['method' => 'UserCountry.getCountry'] + $range($from, $end)))),
            'device' => Breakdowns::perWindow(fn (CarbonImmutable $from, CarbonImmutable $end): array => array_map(
                fn (array $row): array => ['item' => $this->segmentValue($row, 'deviceType') ?? (string) ($row['label'] ?? ''), 'visits' => $this->number($row['nb_visits'] ?? null, 'Matomo')],
                $rows(['method' => 'DevicesDetection.getType'] + $range($from, $end)))),
        ];
        if ($goals !== null) {
            // Each goal's conversions per day, added up per window: conversions add up, so no range report is needed.
            $fetchers['goal'] = function (array $windows) use ($goals, $request, $today): array {
                $earliest = min(array_map(fn (array $window): CarbonImmutable => $window['from'], $windows));
                $byWindow = array_fill(0, count($windows), []);
                foreach ($goals as $goalId => $name) {
                    $days = $this->days($request(['method' => 'Goals.get', 'idGoal' => (string) $goalId, 'period' => 'day', 'date' => $earliest->toDateString().','.$today->toDateString()]));
                    foreach ($windows as $index => $window) {
                        $total = 0.0;
                        foreach ($days as $day => $row) {
                            if ($row !== [] && $day >= $window['from']->toDateString() && $day <= $window['end']->toDateString()) {
                                $total += $this->number($this->object($row, 'Matomo')['nb_conversions'] ?? null, 'Matomo');
                            }
                        }
                        $byWindow[$index][] = ['item' => $name, 'conversions' => $total];
                    }
                }

                return $byWindow;
            };
        }
        $breakdowns = Breakdowns::collect($source->breakdownLists ? $fetchers : [], $today);
        $months = Breakdowns::months($source->breakdownLists ? $fetchers : [], $source->breakdownMonths, $today);

        return new CollectionResult(daily: $daily, channelDays: $channelDays, breakdowns: $breakdowns['rows'], breakdownDimensions: $breakdowns['dimensions'],
            breakdownErrors: $errors + $breakdowns['errors'] + $months['errors'], breakdownMonths: $months['months']);
    }

    /**
     * The channel of a referrer row: Matomo's numeric referrer type, or the segment it adds (referrerType==search).
     *
     * @param  array<string, mixed>  $row
     */
    private function referrerType(array $row): string
    {
        $type = $row['referrer_type'] ?? $row['referer_type'] ?? null;
        $name = match (is_numeric($type) ? (int) $type : null) {
            1 => 'direct', 2 => 'search', 3 => 'website', 6 => 'campaign', 7 => 'social', 8 => 'ai',
            default => $this->segmentValue($row, 'referrerType') ?? (string) ($row['label'] ?? ''),
        };

        return Breakdowns::channel($name);
    }

    /** @param array<string, mixed> $row */
    private function segmentValue(array $row, string $name): ?string
    {
        $segment = $row['segment'] ?? null;

        return is_string($segment) && str_starts_with($segment, $name.'==') ? rawurldecode(substr($segment, strlen($name) + 2)) : null;
    }

    /**
     * The path of an entry page: from its URL when Matomo includes it, otherwise its label.
     *
     * @param  array<string, mixed>  $row
     */
    private function pagePath(array $row): string
    {
        $url = $row['url'] ?? null;
        $path = is_string($url) ? parse_url($url, PHP_URL_PATH) : null;
        if (is_string($path) && $path !== '') {
            return $path;
        }
        $label = trim((string) ($row['label'] ?? ''));

        return $label === '' || str_starts_with($label, '/') ? $label : '/'.$label;
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
