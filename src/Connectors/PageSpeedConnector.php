<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/** Lab snapshots and CrUX rolling windows are observations, never additive traffic totals. */
final class PageSpeedConnector extends AbstractConnector
{
    private const FIELD_METRICS = ['largest_contentful_paint' => 'lcp', 'interaction_to_next_paint' => 'inp', 'cumulative_layout_shift' => 'cls'];

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'Page speed');
        $url = PageSpeedUrl::normalize($source->config['url'] ?? null);
        $strategy = $source->config['strategy'] ?? 'mobile';
        if (! in_array($strategy, ['mobile', 'desktop'], true)) {
            throw new ConnectorException('Choose mobile or desktop for page speed.');
        }
        $key = $this->text($credentials['api_key'] ?? null, 'Google API key', 200);
        $cache = 'page-speed:'.hash('sha256', $key.'|'.$url.'|'.$strategy);
        try {
            return Cache::lock($cache.':lock', 600)->block(5, function () use ($url, $strategy, $key, $cache, $now): CollectionResult {
                $lab = Cache::remember($cache.':lab:'.$now->toDateString(), 129600, fn () => $this->lab($url, $strategy, $key, $now));
                $status = 'available';
                $historyStatus = 'available';
                $field = [];
                $fieldReplace = [];
                try {
                    $field = Cache::remember($cache.':field:'.$now->toDateString(), 86400, fn () => $this->fieldData($url, $strategy, $key, $now, $cache));
                    $status = $field['status'];
                    $historyStatus = $field['history_status'];
                    $fieldReplace = $field['replace'];
                    $field = $field['rows'];
                } catch (\Throwable) {
                    // A field-data outage or missing CrUX permission must not discard a successful lab test.
                    $status = 'unavailable';
                    $field = [];
                }
                foreach ($lab as &$row) {
                    $row['context']['field_status'] = $status;
                    $row['context']['history_status'] = $historyStatus;
                }
                unset($row);
                $replace = [];
                foreach ($lab as $row) {
                    $replace[$row['date']] = ['lab_score', 'lab_lcp', 'lab_cls', 'lab_tbt'];
                }

                return new CollectionResult(daily: array_merge($lab, $field), replaceDailyDates: $this->mergeReplacement($fieldReplace, $replace));
            });
        } catch (ConnectorException|ProviderRequestLimit $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new ConnectorException('Page speed is temporarily unavailable. Try again later.');
        }
    }

    /** @return array{rows: list<array<string,mixed>>, replace: array<string,list<string>>, status: string, history_status: string} */
    private function fieldData(string $url, string $strategy, string $key, CarbonImmutable $now, string $cache): array
    {
        $scope = 'url';
        $record = $this->crux('queryRecord', $scope, $url, $strategy, $key);
        if ($record === null) {
            $scope = 'origin';
            $record = $this->crux('queryRecord', $scope, $url, $strategy, $key);
        }
        $rows = $record === null ? [] : $this->fieldRows($record, $scope, $url, $strategy, $now, false);
        $replace = $record === null ? [] : $this->fieldReplacement($record, $scope, false);
        $historyRows = [];
        $historyReplace = [];
        $historyStatus = 'available';
        // A page may have historical coverage even when its current record is no longer eligible.
        foreach ($scope === 'origin' ? ['url', 'origin'] : ['url'] as $historyScope) {
            try {
                $history = Cache::remember($cache.':history:'.$historyScope.':'.$now->format('o-W'), 604800, function () use ($historyScope, $url, $strategy, $key, $now): array {
                    $history = $this->crux('queryHistoryRecord', $historyScope, $url, $strategy, $key);

                    return $history === null ? ['rows' => [], 'replace' => []] : ['rows' => $this->fieldRows($history, $historyScope, $url, $strategy, $now, true), 'replace' => $this->fieldReplacement($history, $historyScope, true)];
                });
                $historyRows = array_merge($historyRows, $history['rows']);
                $historyReplace = $this->mergeReplacement($historyReplace, $history['replace']);
            } catch (\Throwable) {
                $historyStatus = 'unavailable';
            }
        }

        $out = [];
        foreach (array_merge($historyRows, $rows) as $row) {
            $out[$row['metric'].'|'.$row['date']] = $row;
        }

        return ['rows' => array_values($out), 'replace' => $this->mergeReplacement($historyReplace, $replace), 'status' => $rows ? 'available' : 'no_data', 'history_status' => $historyStatus];
    }

    /** @param array<string,mixed> $record
     * @return array<string,list<string>>
     */
    private function fieldReplacement(array $record, string $scope, bool $history): array
    {
        $replace = [];
        foreach ($history ? $record['collectionPeriods'] : [$record['collectionPeriod']] as $period) {
            $date = $this->cruxDate($period['lastDate'])->toDateString();
            $prefix = $scope === 'origin' ? 'crux_origin_' : 'crux_';
            $replace[$date] = [$prefix.'lcp', $prefix.'inp', $prefix.'cls'];
        }

        return $replace;
    }

    /** @param array<string,list<string>> $one
     * @param  array<string,list<string>>  $two
     * @return array<string,list<string>>
     */
    private function mergeReplacement(array $one, array $two): array
    {
        foreach ($two as $date => $metrics) {
            $one[$date] = array_values(array_unique(array_merge($one[$date] ?? [], $metrics)));
        }

        return $one;
    }

    private function reserve(string $key): void
    {
        $budget = 'page-speed-budget:'.hash('sha256', $key);
        Cache::lock($budget.':lock', 5)->block(2, function () use ($budget): void {
            // Below the shared CrUX/History limit of 150 requests/minute for the operator key.
            if (! RateLimiter::attempt($budget, 100, fn () => true, 60)) {
                throw new ProviderRequestLimit;
            }
        });
    }

    /** @return list<array<string,mixed>> */
    private function lab(string $url, string $strategy, string $key, CarbonImmutable $now): array
    {
        $this->reserve($key);
        $data = $this->json($this->http->request('GET', 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed', [
            'query' => ['url' => $url, 'strategy' => $strategy, 'category' => 'performance', 'key' => $key],
        ], 180), 'PageSpeed Insights');
        $result = $this->object($data['lighthouseResult'] ?? null, 'PageSpeed Insights');
        if (isset($result['runtimeError']) || PageSpeedUrl::normalize($result['requestedUrl'] ?? null) !== $url) {
            throw new ConnectorException('PageSpeed Insights could not analyse the requested page.');
        }
        $device = $result['configSettings']['formFactor'] ?? $result['configSettings']['emulatedFormFactor'] ?? null;
        if ($device !== $strategy) {
            throw new ConnectorException('PageSpeed Insights returned a different device.');
        }
        $time = $this->timestamp($result['fetchTime'] ?? null, 'PageSpeed Insights');
        if ($time->gt($now->addMinutes(10)) || $time->lt($now->subDay())) {
            throw new ConnectorException('PageSpeed Insights returned an unexpected test time.');
        }
        $context = ['type' => 'lab', 'url' => $url, 'audited_url' => PageSpeedUrl::normalize($result['finalUrl'] ?? null), 'device' => $strategy,
            'lighthouse_version' => $this->text($result['lighthouseVersion'] ?? null, 'Lighthouse version', 40), 'tested_at' => $time->toIso8601String()];
        $values = ['lab_score' => 100 * $this->number($result['categories']['performance']['score'] ?? null, 'PageSpeed Insights', 1)];
        foreach (['lab_lcp' => 'largest-contentful-paint', 'lab_cls' => 'cumulative-layout-shift', 'lab_tbt' => 'total-blocking-time'] as $metric => $audit) {
            $values[$metric] = $this->number($result['audits'][$audit]['numericValue'] ?? null, 'PageSpeed Insights');
        }
        $rows = [];
        foreach ($values as $metric => $value) {
            $rows[] = $this->daily($metric, $time->toDateString(), $value) + ['context' => $context];
        }

        return $rows;
    }

    /** @return array<string,mixed>|null */
    private function crux(string $method, string $scope, string $url, string $strategy, string $key): ?array
    {
        $this->reserve($key);
        // CrUX normalizes away queries; lab tests retain the exact user-entered URL.
        $target = $scope === 'origin' ? PageSpeedUrl::origin($url) : explode('?', $url, 2)[0];
        $body = [$scope => $target, 'formFactor' => $strategy === 'mobile' ? 'PHONE' : 'DESKTOP', 'metrics' => array_keys(self::FIELD_METRICS)];
        if ($method === 'queryHistoryRecord') {
            $body['collectionPeriodCount'] = 40;
        }
        $response = $this->http->request('POST', 'https://chromeuxreport.googleapis.com/v1/records:'.$method, [
            'query' => ['key' => $key], 'json' => $body,
        ]);
        if ($response->status() === 404 && $response->json('error.status') === 'NOT_FOUND') {
            return null;
        }
        $data = $this->json($response, 'CrUX');
        $record = $this->object($data['record'] ?? null, 'CrUX');
        if (($record['key'][$scope] ?? null) !== $target || ($record['key']['formFactor'] ?? null) !== $body['formFactor']) {
            throw new ConnectorException('CrUX returned a different page or device.');
        }

        return $record;
    }

    /** @param array<string,mixed> $record
     * @return list<array<string,mixed>>
     */
    private function fieldRows(array $record, string $scope, string $url, string $strategy, CarbonImmutable $now, bool $history): array
    {
        $periods = $history ? ($record['collectionPeriods'] ?? null) : [$record['collectionPeriod'] ?? null];
        if (! is_array($periods) || ! array_is_list($periods) || count($periods) > 40 || ! $periods) {
            throw new ConnectorException('CrUX returned invalid collection periods.');
        }
        $metrics = $this->object($record['metrics'] ?? null, 'CrUX');
        $rows = [];
        $last = null;
        foreach ($periods as $index => $period) {
            if (! is_array($period)) {
                throw new ConnectorException('CrUX returned an invalid reporting window.');
            }
            $start = $this->cruxDate($period['firstDate'] ?? null);
            $end = $this->cruxDate($period['lastDate'] ?? null);
            if ($start->addDays(27)->ne($end) || $end->gt($now->startOfDay()) || ($last !== null && $end->lte($last))) {
                throw new ConnectorException('CrUX returned an invalid reporting window.');
            }
            $last = $end;
            foreach (self::FIELD_METRICS as $external => $name) {
                if (! isset($metrics[$external])) {
                    continue;
                }
                if ($history) {
                    $values = $metrics[$external]['percentilesTimeseries']['p75s'] ?? null;
                    if (! is_array($values) || ! array_is_list($values) || count($values) !== count($periods)) {
                        throw new ConnectorException('CrUX returned invalid history.');
                    }
                    $value = $values[$index];
                } else {
                    $value = $metrics[$external]['percentiles']['p75'] ?? null;
                }
                if ($value === null) {
                    continue;
                }
                $metric = ($scope === 'origin' ? 'crux_origin_' : 'crux_').$name;
                $rows[] = $this->daily($metric, $end->toDateString(), $this->number($value, 'CrUX')) + [
                    'window_start' => $start->toIso8601String(), 'window_end' => $end->endOfDay()->toIso8601String(),
                    'context' => ['type' => 'field', 'scope' => $scope, 'url' => $record['key'][$scope], 'device' => $strategy, 'percentile' => 75,
                        'collection_start' => $start->toDateString(), 'collection_end' => $end->toDateString()],
                ];
            }
        }

        return $rows;
    }

    private function cruxDate(mixed $date): CarbonImmutable
    {
        if (! is_array($date) || ! is_int($date['year'] ?? null) || ! is_int($date['month'] ?? null) || ! is_int($date['day'] ?? null)) {
            throw new ConnectorException('CrUX returned an invalid date.');
        }
        $value = $this->date(sprintf('%04d-%02d-%02d', $date['year'], $date['month'], $date['day']), 'CrUX');

        return CarbonImmutable::parse($value, 'UTC');
    }
}
