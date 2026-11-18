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
        $rows = [];
        foreach ($dates as $date => $values) {
            foreach ($values as $metric => $value) {
                $rows[] = $this->daily($metric, $date, $value, $date === $today->toDateString());
            }
        }

        return new CollectionResult(daily: $rows, periods: $event === null ? $this->periods($client, $base, $key, $id, $source, $today) : []);
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
