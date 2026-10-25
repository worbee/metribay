<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

final class Ga4Connector extends AbstractConnector
{
    private const METRICS = ['totalUsers' => 'visitors', 'sessions' => 'visits', 'screenPageViews' => 'pageviews'];

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $property = $source->config['property_id'] ?? null;
        if ((! is_int($property) && ! is_string($property)) || ! preg_match('/^[1-9][0-9]*$/D', (string) $property)) {
            throw new ConnectorException('Use the numeric GA4 property ID, not a G- measurement ID.');
        }
        [$start, $today] = $this->window($source, $now, 7);
        $windows = $this->periodWindows($source, $today);
        $token = (new GoogleToken($this->http))->fetch($credentials, 'https://www.googleapis.com/auth/analytics.readonly');
        $report = function (CarbonImmutable $from, CarbonImmutable $end, bool $daily) use ($property, $source, $token): array {
            $metrics = $daily ? array_keys(self::METRICS) : ['totalUsers'];
            $data = $this->json($this->http->request('POST', 'https://analyticsdata.googleapis.com/v1beta/properties/'.$property.':runReport', [
                'headers' => ['Authorization' => 'Bearer '.$token],
                'json' => [
                    'dateRanges' => [['startDate' => $from->toDateString(), 'endDate' => $end->toDateString()]],
                    'dimensions' => $daily ? [['name' => 'date']] : [],
                    'metrics' => array_map(fn (string $name): array => ['name' => $name], $metrics),
                    'keepEmptyRows' => true, 'limit' => '250000',
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
            foreach (['dataLossFromOtherRow', 'samplingMetadatas', 'dataTruncationReasons', 'subjectToThresholding', 'emptyReason'] as $flag) {
                if (! empty($metadata[$flag])) {
                    throw new ConnectorException('GA4 returned sampled, restricted, thresholded, or incomplete data.');
                }
            }
            if (! empty($restriction['activeMetricRestrictions'])) {
                throw new ConnectorException('GA4 returned restricted metric data.');
            }
            $rows = $this->list($data['rows'] ?? [], 'GA4');
            if ($this->count($data['rowCount'] ?? 0, 'GA4') !== count($rows)) {
                throw new ConnectorException('GA4 returned a truncated or inconsistent report.');
            }
            foreach (['metricHeaders' => $metrics, 'dimensionHeaders' => $daily ? ['date'] : []] as $field => $expected) {
                if ($rows !== [] || array_key_exists($field, $data)) {
                    // Protobuf JSON omits repeated fields when empty (dimensionless totals).
                    $headers = $this->list($data[$field] ?? [], 'GA4');
                    $names = array_map(fn (mixed $header): mixed => $this->object($header, 'GA4')['name'] ?? null, $headers);
                    if ($names !== $expected) {
                        throw new ConnectorException('GA4 returned unexpected report headers.');
                    }
                }
            }

            return $rows;
        };
        $daily = [];
        $seen = [];
        foreach ($report($start, $today, true) as $raw) {
            $row = $this->object($raw, 'GA4');
            $dimensions = $this->list($row['dimensionValues'] ?? null, 'GA4');
            $rawDate = $this->object($dimensions[0] ?? null, 'GA4')['value'] ?? null;
            if (count($dimensions) !== 1 || ! is_string($rawDate) || ! preg_match('/^[0-9]{8}$/D', $rawDate)) {
                throw new ConnectorException('GA4 returned an invalid daily dimension.');
            }
            $date = $this->date(substr($rawDate, 0, 4).'-'.substr($rawDate, 4, 2).'-'.substr($rawDate, 6, 2), 'GA4');
            if ($date < $start->toDateString() || $date > $today->toDateString() || isset($seen[$date])) {
                throw new ConnectorException('GA4 returned a duplicate or out-of-window date.');
            }
            $seen[$date] = true;
            $values = $this->values($row, 3);
            foreach (array_values(self::METRICS) as $index => $metric) {
                // Three-day provisional tail is a local revision policy, not Google finality.
                $daily[] = $this->daily($metric, $date, $values[$index], $date > $today->subDays(3)->toDateString());
            }
        }
        $periods = [];
        foreach ($windows as [$from, $end]) {
            $rows = $report($from, $end, false);
            if (count($rows) > 1) {
                throw new ConnectorException('GA4 returned multiple rows for a unique-user total.');
            }
            if ($rows === []) {
                continue; // No provider row is unknown, including in an empty property.
            }
            $row = $this->object($rows[0], 'GA4');
            if (($row['dimensionValues'] ?? []) !== []) {
                throw new ConnectorException('GA4 returned unexpected total dimensions.');
            }
            $periods[] = ['metric' => 'visitors', 'start_date' => $from->toDateString(), 'end_date' => $end->toDateString(),
                'value' => $this->values($row, 1)[0], 'is_partial' => $end > $today->subDays(3)];
        }

        return new CollectionResult(daily: $daily, periods: $periods);
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
