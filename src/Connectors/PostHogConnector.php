<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/**
 * PostHog through HogQL queries on the project's events, in the project timezone.
 *
 * Without an event in the configuration the source records website and product usage: visitors (people with a page view),
 * visits (sessions with a page view), page views and active users (people with any event). Visitors and active users
 * over 7, 30 and 90 days come from one separate query, because people are counted once per window, not once per day.
 * With an event it records only that event's daily count as key events.
 *
 * Days after the project was created that have no events are real zeros. Earlier days and days PostHog did not answer stay unknown.
 */
final class PostHogConnector extends AbstractConnector
{
    /** Bounds the daily result; the collection window is at most 366 days plus the revision overlap. */
    private const DAILY_LIMIT = 1000;

    private const USAGE = ['visitors', 'visits', 'pageviews', 'active_users'];

    /** Bounds a breakdown result: days times channels, or the items of one dimension. */
    private const BREAKDOWN_LIMIT = 10000;

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $base = PostHogClient::base($credentials['base_url'] ?? null);
        $key = $this->text($credentials['api_key'] ?? null, 'PostHog personal API key', 500);
        $id = PostHogClient::projectId($source->config['project_id'] ?? null);
        $event = array_key_exists('event', $source->config) ? self::event($source->config['event']) : null;
        [$start, $today] = $this->window($source, $now);
        $client = new PostHogClient($this->http);
        $project = $this->project($client, $base, $key, $id);
        $timezone = $this->text($project['timezone'] ?? null, 'PostHog project timezone', 64);
        if (! $this->reportingTimezoneMatches($timezone, $source->reportingTimezone)) {
            throw new ConnectorException('The PostHog project timezone does not match this source reporting timezone.');
        }
        $created = null;
        if (isset($project['created_at'])) {
            try {
                $created = $this->timestamp($project['created_at'], 'PostHog')->setTimezone($source->reportingTimezone)->startOfDay();
            } catch (ConnectorException) {
                // Without a creation date, days without events stay unknown instead of zero.
            }
        }
        if ($event !== null) {
            $this->requireEvent($client, $base, $key, $id, $event);
        }
        $metrics = $event === null ? self::USAGE : ['key_events'];
        $range = ['from' => $start->format('Y-m-d H:i:s'), 'to' => $today->addDay()->format('Y-m-d H:i:s'), 'tz' => $source->reportingTimezone];
        $daily = $event === null
            ? $this->query($client, $base, $key, $id, "SELECT toString(toDate(toTimeZone(timestamp, {tz}))) AS day, uniqIf(person_id, event = '\$pageview') AS visitors, "
                ."uniqIf(properties.\$session_id, event = '\$pageview') AS visits, countIf(event = '\$pageview') AS pageviews, uniq(person_id) AS active_users "
                .'FROM events WHERE timestamp >= toDateTime({from}, {tz}) AND timestamp < toDateTime({to}, {tz}) GROUP BY day ORDER BY day ASC LIMIT '.self::DAILY_LIMIT,
                $range, ['day', ...self::USAGE])
            : $this->query($client, $base, $key, $id, 'SELECT toString(toDate(toTimeZone(timestamp, {tz}))) AS day, count() AS key_events '
                .'FROM events WHERE event = {event} AND timestamp >= toDateTime({from}, {tz}) AND timestamp < toDateTime({to}, {tz}) GROUP BY day ORDER BY day ASC LIMIT '.self::DAILY_LIMIT,
                $range + ['event' => $event], ['day', 'key_events']);
        if (count($daily) >= self::DAILY_LIMIT) {
            throw new ConnectorException('PostHog returned a truncated daily report.');
        }
        $dates = [];
        foreach ($daily as $row) {
            $date = $this->date($row[0], 'PostHog');
            if (isset($dates[$date]) || $date < $start->toDateString() || $date > $today->toDateString()) {
                throw new ConnectorException('PostHog returned a duplicate or out-of-window date.');
            }
            foreach ($metrics as $index => $metric) {
                $dates[$date][$metric] = (float) $this->count($row[$index + 1], 'PostHog');
            }
        }
        if ($created !== null) {
            // PostHog stores every event, so a quiet day after the project existed is zero. The creation day itself may be incomplete.
            for ($day = $start->max($created->addDay()); $day < $today; $day = $day->addDay()) {
                $dates[$day->toDateString()] ??= array_fill_keys($metrics, 0.0);
            }
        }
        ksort($dates);
        $periods = $event === null ? $this->periods($client, $base, $key, $id, $source, $today) : [];
        $errors = [];
        $channelDays = null;
        $breakdowns = ['rows' => [], 'dimensions' => [], 'errors' => []];
        $months = ['months' => [], 'errors' => []];
        if ($event === null && ($source->config['breakdowns'] ?? true) !== false) {
            try {
                // Sessions with a page view: channel, bounce and length, by the day the session started.
                $sessions = $this->query($client, $base, $key, $id, 'SELECT toString(toDate(toTimeZone($start_timestamp, {tz}))) AS day, toString($channel_type) AS channel, count() AS visits, '
                    .'countIf($is_bounce) AS bounces, sum($session_duration) AS duration FROM sessions WHERE $start_timestamp >= toDateTime({from}, {tz}) AND $start_timestamp < toDateTime({to}, {tz}) '
                    .'AND $pageview_count > 0 GROUP BY day, channel ORDER BY day ASC LIMIT '.self::BREAKDOWN_LIMIT, $range, ['day', 'channel', 'visits', 'bounces', 'duration']);
                if (count($sessions) >= self::BREAKDOWN_LIMIT) {
                    throw new ConnectorException('PostHog returned a truncated report.');
                }
                $channels = [];
                $engagement = array_fill_keys(array_keys($dates), ['bounces' => 0.0, 'visit_duration' => 0.0]);
                foreach ($sessions as [$day, $channel, $visits, $bounces, $duration]) {
                    $day = $this->date($day, 'PostHog');
                    if (! isset($engagement[$day])) {
                        throw new ConnectorException('PostHog returned a duplicate or out-of-window date.');
                    }
                    $channels[] = ['date' => $day, 'channel' => is_string($channel) ? $channel : '', 'visits' => (float) $this->count($visits, 'PostHog')];
                    $engagement[$day]['bounces'] += $this->count($bounces, 'PostHog');
                    $engagement[$day]['visit_duration'] += $this->number($duration, 'PostHog');
                }
                $channelDays = Breakdowns::channelDays($channels, array_keys($dates), $today);
                foreach ($engagement as $day => $values) {
                    $dates[$day] += $values;
                }
            } catch (ConnectorException $exception) {
                $errors['channel_days'] = $exception->getMessage();
            }
            $fetchers = $source->breakdownLists ? $this->breakdownFetchers($client, $base, $key, $id, $source->reportingTimezone, $today) : [];
            $breakdowns = Breakdowns::collect($fetchers, $today);
            $months = Breakdowns::months($fetchers, $source->breakdownMonths, $today);
        }
        $rows = [];
        foreach ($dates as $date => $values) {
            foreach ($values as $metric => $value) {
                $rows[] = $this->daily($metric, $date, $value, $date === $today->toDateString());
            }
        }

        return new CollectionResult(daily: $rows, periods: $periods, channelDays: $channelDays, breakdowns: $breakdowns['rows'],
            breakdownDimensions: $breakdowns['dimensions'], breakdownErrors: $errors + $breakdowns['errors'] + $months['errors'], breakdownMonths: $months['months']);
    }

    /**
     * One query per dimension that counts every reporting window at once. Session dimensions (referrer, landing page,
     * campaign) come from the sessions table and count visits; country and device come from page views and also count people.
     *
     * @return array<string, \Closure(list<array{days: int, previous: bool, from: CarbonImmutable, end: CarbonImmutable}>): array<int, list<array<string, mixed>>>>
     */
    private function breakdownFetchers(PostHogClient $client, string $base, string $key, string $id, string $timezone, CarbonImmutable $today): array
    {
        $fetchers = [];
        $dimensions = ['referrer' => ['sessions', 'toString($entry_referring_domain)'], 'landing_page' => ['sessions', 'toString($entry_pathname)'],
            'campaign' => ['sessions', 'toString($entry_utm_campaign)'], 'country' => ['events', 'toString(properties.$geoip_country_code)'], 'device' => ['events', 'toString(properties.$device_type)']];
        foreach ($dimensions as $dimension => [$table, $expression]) {
            $fetchers[$dimension] = function (array $windows) use ($client, $base, $key, $id, $timezone, $today, $table, $expression): array {
                $time = $table === 'sessions' ? '$start_timestamp' : 'timestamp';
                $values = ['tz' => $timezone, 'to' => $today->addDay()->format('Y-m-d H:i:s')];
                $columns = ['item'];
                $select = [$expression.' AS item'];
                $earliest = $today;
                foreach ($windows as $index => $window) {
                    $values['from'.$index] = $window['from']->format('Y-m-d H:i:s');
                    $values['to'.$index] = $window['end']->addDay()->format('Y-m-d H:i:s');
                    $within = "{$time} >= toDateTime({from{$index}}, {tz}) AND {$time} < toDateTime({to{$index}}, {tz})";
                    $select[] = $table === 'sessions' ? "countIf({$within}) AS visits_{$index}" : "uniqIf(properties.\$session_id, {$within}) AS visits_{$index}";
                    $columns[] = 'visits_'.$index;
                    if ($table === 'events') {
                        $select[] = "uniqIf(person_id, {$within}) AS visitors_{$index}";
                        $columns[] = 'visitors_'.$index;
                    }
                    $earliest = $earliest->min($window['from']);
                }
                $values['from'] = $earliest->format('Y-m-d H:i:s');
                $where = $table === 'sessions' ? '$pageview_count > 0' : "event = '\$pageview'";
                $rows = $this->query($client, $base, $key, $id, 'SELECT '.implode(', ', $select)." FROM {$table} WHERE {$where} AND {$time} >= toDateTime({from}, {tz}) AND {$time} < toDateTime({to}, {tz}) "
                    .'GROUP BY item ORDER BY count() DESC LIMIT '.self::BREAKDOWN_LIMIT, $values, $columns);
                $byWindow = array_fill(0, count($windows), []);
                $stride = $table === 'events' ? 2 : 1;
                foreach ($rows as $row) {
                    $item = $row[0];
                    // PostHog marks visits without a referrer as "$direct".
                    if (! is_string($item) || $item === '' || $item === '$direct') {
                        continue;
                    }
                    foreach (array_keys($windows) as $index) {
                        $counts = ['item' => $item, 'visits' => (float) $this->count($row[1 + $stride * $index], 'PostHog')];
                        if ($stride === 2) {
                            $counts['visitors'] = (float) $this->count($row[2 + $stride * $index], 'PostHog');
                        }
                        $byWindow[$index][] = $counts;
                    }
                }

                return $byWindow;
            };
        }

        return $fetchers;
    }

    /**
     * An event name as PostHog shows it. Names are matched exactly, so surrounding spaces are removed.
     */
    public static function event(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 200 || preg_match('/[\x00-\x1F\x7F]/u', $value) || ! mb_check_encoding($value, 'UTF-8')) {
            throw new ConnectorException('Use the PostHog event name, such as user_signed_up.');
        }

        return trim($value);
    }

    /**
     * Visitors and active users per reporting window, counted once per window, all in one query.
     *
     * @return list<array{metric: string, start_date: string, end_date: string, value: float, is_partial: bool}>
     */
    private function periods(PostHogClient $client, string $base, string $key, string $id, SourceConfiguration $source, CarbonImmutable $today): array
    {
        $windows = $this->periodWindows($source, $today);
        if ($windows === []) {
            return [];
        }
        $values = ['tz' => $source->reportingTimezone];
        $columns = [];
        $names = [];
        $earliest = $today;
        foreach ($windows as $index => [$from, $end]) {
            $values['from'.$index] = $from->format('Y-m-d H:i:s');
            $values['to'.$index] = $end->addDay()->format('Y-m-d H:i:s');
            $within = 'timestamp >= toDateTime({from'.$index.'}, {tz}) AND timestamp < toDateTime({to'.$index.'}, {tz})';
            $columns[] = "uniqIf(person_id, event = '\$pageview' AND {$within}) AS visitors_{$index}";
            $columns[] = "uniqIf(person_id, {$within}) AS active_users_{$index}";
            $names[] = 'visitors_'.$index;
            $names[] = 'active_users_'.$index;
            $earliest = $earliest->min($from);
        }
        $values['from'] = $earliest->format('Y-m-d H:i:s');
        $values['to'] = $today->addDay()->format('Y-m-d H:i:s');
        $rows = $this->query($client, $base, $key, $id, 'SELECT '.implode(', ', $columns).' FROM events WHERE timestamp >= toDateTime({from}, {tz}) AND timestamp < toDateTime({to}, {tz})', $values, $names);
        if (count($rows) !== 1) {
            throw new ConnectorException('PostHog returned unexpected period totals.');
        }
        $periods = [];
        foreach ($windows as $index => [$from, $end]) {
            foreach (['visitors', 'active_users'] as $offset => $metric) {
                $periods[] = ['metric' => $metric, 'start_date' => $from->toDateString(), 'end_date' => $end->toDateString(),
                    'value' => (float) $this->count($rows[0][2 * $index + $offset], 'PostHog'), 'is_partial' => $end->equalTo($today)];
            }
        }

        return $periods;
    }

    /**
     * Runs one HogQL query. Event names and dates are passed as placeholders, never spliced into the query text.
     *
     * @param  array<string, string>  $values
     * @param  list<string>  $columns
     * @return list<list<mixed>>
     */
    private function query(PostHogClient $client, string $base, string $key, string $id, string $hogql, array $values, array $columns): array
    {
        $data = $this->decoded($client->request('POST', $base, $key, '/api/projects/'.$id.'/query/', ['json' => [
            'query' => ['kind' => 'HogQLQuery', 'query' => $hogql, 'values' => $values],
            'name' => 'metribay',
        ]]));
        if (($data['columns'] ?? null) !== $columns) {
            throw new ConnectorException('PostHog returned unexpected columns.');
        }
        if (($data['hasMore'] ?? false) === true) {
            throw new ConnectorException('PostHog returned a truncated report.');
        }
        $rows = [];
        foreach ($this->list($data['results'] ?? null, 'PostHog') as $row) {
            $row = $this->list($row, 'PostHog');
            if (count($row) !== count($columns)) {
                throw new ConnectorException('PostHog returned an unexpected row.');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function project(PostHogClient $client, string $base, string $key, string $id): array
    {
        $response = $client->request('GET', $base, $key, '/api/projects/'.$id.'/');
        if ($response->status() === 404) {
            throw new ConnectorException('The PostHog project is not visible to this API key.');
        }
        $project = $this->decoded($response);
        if ((string) ($project['id'] ?? '') !== $id) {
            throw new ConnectorException('PostHog returned a different project.');
        }

        return $project;
    }

    /** An unknown event would count zero every day, so a misspelt name fails instead. */
    private function requireEvent(PostHogClient $client, string $base, string $key, string $id, string $event): void
    {
        $data = $this->decoded($client->request('GET', $base, $key, '/api/projects/'.$id.'/event_definitions/', ['query' => ['search' => $event, 'limit' => 100]]));
        foreach ($this->list($data['results'] ?? null, 'PostHog') as $definition) {
            if (($this->object($definition, 'PostHog')['name'] ?? null) === $event) {
                return;
            }
        }

        throw new ConnectorException('PostHog has not seen this event in the project. Check the event name.');
    }

    /** @return array<string, mixed> */
    private function decoded(Response $response): array
    {
        $message = match ($response->status()) {
            200 => null,
            401, 403 => 'PostHog rejected the personal API key. Check that it can read this project, with the project, query and event definition read scopes.',
            429 => 'PostHog is rate limiting requests. Scheduled imports will retry.',
            default => 'PostHog returned HTTP '.$response->status().'. Check the API key, project ID and server URL.',
        };
        if ($message !== null) {
            throw new ConnectorException($message);
        }

        return $this->json($response, 'PostHog');
    }
}
