<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

final class Ga4Connector extends AbstractConnector
{
    private const METRICS = ['totalUsers', 'sessions', 'screenPageViews', 'engagedSessions', 'averageSessionDuration', 'keyEvents'];

    /** Breakdown dimensions and the GA4 dimension that answers each. */
    private const DIMENSIONS = ['referrer' => 'sessionSource', 'landing_page' => 'landingPage', 'campaign' => 'sessionCampaignName',
        'country' => 'countryId', 'device' => 'deviceCategory', 'goal' => 'eventName'];

    /** GA4 keeps revising the last three days; Metribay marks them provisional. */
    private const PROVISIONAL_DAYS = 3;

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $property = $source->config['property_id'] ?? null;
        if ((! is_int($property) && ! is_string($property)) || ! preg_match('/^[1-9][0-9]*$/D', (string) $property)) {
            throw new ConnectorException('Use the numeric GA4 property ID, not a G- measurement ID.');
        }
        [$start, $today] = $this->window($source, $now, 7);
        $windows = $this->periodWindows($source, $today);
        $token = (new GoogleToken($this->http))->fetch($credentials, 'https://www.googleapis.com/auth/analytics.readonly');
        /*
         * One report. Strict reports (daily metrics, totals and channels) must be complete and unsampled. Top lists may
         * leave rare items out (thresholding, the "(other)" row, the row limit), because they only list the largest ones.
         */
        $report = function (CarbonImmutable $from, CarbonImmutable $end, array $dimensions, array $metrics, bool $strict, array $extra = []) use ($property, $source, $token): array {
            $data = $this->json($this->http->request('POST', 'https://analyticsdata.googleapis.com/v1beta/properties/'.$property.':runReport', [
                'headers' => ['Authorization' => 'Bearer '.$token],
                'json' => $extra + [
                    'dateRanges' => [['startDate' => $from->toDateString(), 'endDate' => $end->toDateString()]],
                    'dimensions' => array_map(fn (string $name): array => ['name' => $name], $dimensions),
                    'metrics' => array_map(fn (string $name): array => ['name' => $name], $metrics),
                    'keepEmptyRows' => $strict, 'limit' => '250000',
                ],
            ]), 'GA4');
            $metadata = $this->object($data['metadata'] ?? null, 'GA4');
            $actualTimezone = $this->text($metadata['timeZone'] ?? null, 'GA4 property timezone');
            $utcAliases = ['UTC', 'Etc/UTC', 'Etc/GMT', 'GMT'];
            if ($actualTimezone !== $source->reportingTimezone
                && (! in_array($actualTimezone, $utcAliases, true) || ! in_array($source->reportingTimezone, $utcAliases, true))) {
                throw new ConnectorException('The GA4 property timezone does not match this source reporting timezone.');
            }
            $restriction = $this->object($metadata['schemaRestrictionResponse'] ?? [], 'GA4');
            foreach ($strict ? ['dataLossFromOtherRow', 'samplingMetadatas', 'dataTruncationReasons', 'subjectToThresholding', 'emptyReason'] : ['samplingMetadatas', 'emptyReason'] as $flag) {
                if (! empty($metadata[$flag])) {
                    throw new ConnectorException('GA4 returned sampled, restricted, thresholded, or incomplete data.');
                }
            }
            if (! empty($restriction['activeMetricRestrictions'])) {
                throw new ConnectorException('GA4 returned restricted metric data.');
            }
            $rows = $this->list($data['rows'] ?? [], 'GA4');
            if ($strict && $this->count($data['rowCount'] ?? 0, 'GA4') !== count($rows)) {
                throw new ConnectorException('GA4 returned a truncated or inconsistent report.');
            }
            foreach (['metricHeaders' => $metrics, 'dimensionHeaders' => $dimensions] as $field => $expected) {
                if ($rows !== [] || array_key_exists($field, $data)) {
                    // Protobuf JSON omits repeated fields when empty (dimensionless totals).
                    $headers = $this->list($data[$field] ?? [], 'GA4');
                    $names = array_map(fn (mixed $header): mixed => $this->object($header, 'GA4')['name'] ?? null, $headers);
                    if ($names !== $expected) {
                        throw new ConnectorException('GA4 returned unexpected report headers.');
                    }
                }
            }
            $parsed = [];
            foreach ($rows as $raw) {
                $row = $this->object($raw, 'GA4');
                $values = $this->list($row['dimensionValues'] ?? [], 'GA4');
                if (count($values) !== count($dimensions)) {
                    throw new ConnectorException('GA4 returned an invalid dimension.');
                }
                $parsed[] = ['dimensions' => array_map(function (mixed $value): string {
                    $text = $this->object($value, 'GA4')['value'] ?? null;
                    if (! is_string($text)) {
                        throw new ConnectorException('GA4 returned an invalid dimension.');
                    }

                    return $text;
                }, $values), 'metrics' => $this->values($row, count($metrics))];
            }

            return $parsed;
        };
        $date = function (string $raw) use ($start, $today): string {
            if (! preg_match('/^[0-9]{8}$/D', $raw)) {
                throw new ConnectorException('GA4 returned an invalid daily dimension.');
            }
            $date = $this->date(substr($raw, 0, 4).'-'.substr($raw, 4, 2).'-'.substr($raw, 6, 2), 'GA4');
            if ($date < $start->toDateString() || $date > $today->toDateString()) {
                throw new ConnectorException('GA4 returned a duplicate or out-of-window date.');
            }

            return $date;
        };
        $provisional = $today->subDays(self::PROVISIONAL_DAYS)->toDateString();
        $daily = [];
        $seen = [];
        $conversions = [];
        foreach ($report($start, $today, ['date'], self::METRICS, true) as $row) {
            $day = $date($row['dimensions'][0]);
            if (isset($seen[$day])) {
                throw new ConnectorException('GA4 returned a duplicate or out-of-window date.');
            }
            $seen[$day] = true;
            [$users, $sessions, $views, $engaged, $duration, $keyEvents] = $row['metrics'];
            // A bounce in GA4 is a session that was not engaged. The average duration times the sessions is their total length.
            $values = ['visitors' => $users, 'visits' => $sessions, 'pageviews' => $views, 'bounces' => max(0.0, $sessions - $engaged), 'visit_duration' => round($duration * $sessions, 3)];
            foreach ($values as $metric => $value) {
                // Three-day provisional tail is a local revision policy, not Google finality.
                $daily[] = $this->daily($metric, $day, $value, $day > $provisional);
            }
            $conversions[$day] = $keyEvents;
        }
        // A property without key events reports zero every day; it records no conversions rather than a misleading zero chart.
        if (array_sum($conversions) > 0) {
            foreach ($conversions as $day => $value) {
                $daily[] = $this->daily('conversions', $day, $value, $day > $provisional);
            }
        }
        $periods = [];
        foreach ($windows as [$from, $end]) {
            $rows = $report($from, $end, [], ['totalUsers'], true);
            if (count($rows) > 1) {
                throw new ConnectorException('GA4 returned multiple rows for a unique-user total.');
            }
            if ($rows === []) {
                continue; // No provider row is unknown, including in an empty property.
            }
            if ($rows[0]['dimensions'] !== []) {
                throw new ConnectorException('GA4 returned unexpected total dimensions.');
            }
            $periods[] = ['metric' => 'visitors', 'start_date' => $from->toDateString(), 'end_date' => $end->toDateString(),
                'value' => $rows[0]['metrics'][0], 'is_partial' => $end > $today->subDays(3)];
        }
        if (($source->config['breakdowns'] ?? true) === false) {
            return new CollectionResult(daily: $daily, periods: $periods);
        }

        $errors = [];
        $channelDays = null;
        try {
            $channels = [];
            foreach ($report($start, $today, ['date', 'sessionDefaultChannelGroup'], ['sessions'], true) as $row) {
                $channels[] = ['date' => $date($row['dimensions'][0]), 'channel' => $row['dimensions'][1], 'visits' => $row['metrics'][0]];
            }
            $channelDays = Breakdowns::channelDays($channels, array_keys($seen), $today, self::PROVISIONAL_DAYS);
        } catch (ConnectorException $exception) {
            $errors['channel_days'] = $exception->getMessage();
        }
        $fetchers = [];
        foreach (self::DIMENSIONS as $dimension => $name) {
            $metrics = $dimension === 'goal' ? ['keyEvents', 'totalUsers'] : ['sessions', 'totalUsers'];
            $fetchers[$dimension] = Breakdowns::perWindow(function (CarbonImmutable $from, CarbonImmutable $end) use ($report, $dimension, $name, $metrics): array {
                $rows = $report($from, $end, [$name], $metrics, false, ['limit' => (string) Breakdowns::LIMIT, 'orderBys' => [['metric' => ['metricName' => $metrics[0]], 'desc' => true]]]);
                $items = [];
                foreach ($rows as $row) {
                    $item = $row['dimensions'][0];
                    // "(not set)", "(direct)", "(organic)": GA4's placeholders, not referrers, pages or campaigns.
                    if (! in_array($dimension, ['country', 'device'], true) && preg_match('/^\(.*\)$/D', $item)) {
                        continue;
                    }
                    $items[] = $dimension === 'goal'
                        ? ['item' => $item, 'conversions' => $row['metrics'][0], 'visitors' => $row['metrics'][1]]
                        : ['item' => $item, 'visits' => $row['metrics'][0], 'visitors' => $row['metrics'][1]];
                }

                return $items;
            });
        }
        $breakdowns = Breakdowns::collect($source->breakdownLists ? $fetchers : [], $today, self::PROVISIONAL_DAYS);

        return new CollectionResult(daily: $daily, periods: $periods, channelDays: $channelDays, breakdowns: $breakdowns['rows'],
            breakdownDimensions: $breakdowns['dimensions'], breakdownErrors: $errors + $breakdowns['errors']);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<float>
     */
    private function values(array $row, int $count): array
    {
        $values = $this->list($row['metricValues'] ?? null, 'GA4');
        if (count($values) !== $count) {
            throw new ConnectorException('GA4 returned an invalid metric column count.');
        }

        return array_map(fn (mixed $value): float => $this->number($this->object($value, 'GA4')['value'] ?? null, 'GA4'), $values);
    }
}
