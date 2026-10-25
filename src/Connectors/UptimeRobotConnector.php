<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/** These are observed status/trailing-24h snapshots, never reconstructed daily uptime. */
final class UptimeRobotConnector extends AbstractConnector
{
    private const STATUS = ['PAUSED' => 0, 'STARTED' => 1, 'NOT_CHECKED_YET' => 1, 'UP' => 2,
        'LOOKS_DOWN' => 8, 'SEEMS_DOWN' => 8, 'DOWN' => 9];

    private readonly UptimeRobotRateLimiter $limiter;

    public function __construct(SafeHttpClient $http, ?UptimeRobotRateLimiter $limiter = null)
    {
        parent::__construct($http);
        $this->limiter = $limiter ?? new UptimeRobotRateLimiter;
    }

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'UptimeRobot');
        $id = $source->config['monitor_id'] ?? null;
        if ((! is_string($id) && ! is_int($id)) || ! preg_match('/^[1-9][0-9]{0,17}$/D', (string) $id)) {
            throw new ConnectorException('Use a positive UptimeRobot monitor ID.');
        }
        $token = $this->text($credentials['api_key'] ?? $credentials['key'] ?? null, 'UptimeRobot API key');
        $end = $now->utc()->setMicrosecond(0);
        $start = $end->subDay();
        $query = ['from' => $start->toIso8601String(), 'to' => $end->toIso8601String()];
        $get = function (string $path, array $parameters = []) use ($token): array {
            $this->limiter->wait($token);

            return $this->json($this->http->request('GET', 'https://api.uptimerobot.com/v3'.$path, [
                'headers' => ['Authorization' => 'Bearer '.$token], 'query' => $parameters,
            ]), 'UptimeRobot');
        };
        $path = '/monitors/'.$id;
        $monitor = $get($path);
        if (! is_int($monitor['id'] ?? null) || $monitor['id'] !== (int) $id) {
            throw new ConnectorException('UptimeRobot returned a different monitor.');
        }
        $type = $this->text($monitor['type'] ?? null, 'UptimeRobot monitor type', 40);
        $status = $monitor['status'] ?? null;
        if (is_string($status)) {
            $status = self::STATUS[$status] ?? null;
        }
        if (! is_int($status) || ! in_array($status, [0, 1, 2, 8, 9], true)) {
            throw new ConnectorException('UptimeRobot returned an unknown monitor status.');
        }
        $uptime = $get($path.'/stats/uptime', $query);
        $this->checkWindow($uptime, $start, $end);
        $ratio = $this->number($uptime['uptime'] ?? null, 'UptimeRobot', 100);
        $average = null;
        if ($type !== 'HEARTBEAT') {
            $response = $get($path.'/stats/response-time', $query);
            $this->checkWindow($response, $start, $end);
            $points = $this->count($response['data_points'] ?? null, 'UptimeRobot');
            $summary = $this->object($response['summary'] ?? null, 'UptimeRobot');
            if ($points > 0) {
                $average = $this->number($summary['avg'] ?? null, 'UptimeRobot');
            } elseif (($summary['avg'] ?? null) !== null) {
                throw new ConnectorException('UptimeRobot returned response-time statistics without samples.');
            }
        }
        $date = $end->toDateString();
        $daily = [$this->daily('monitor_status', $date, (float) $status)];
        if (! in_array($status, [0, 1], true)) {
            $daily[] = $this->daily('uptime_24h', $date, $ratio);
        }
        if ($average !== null) {
            $daily[] = $this->daily('response_time_24h', $date, $average);
        }

        return new CollectionResult(daily: $daily,
            replaceDailyDates: [$date => ['monitor_status', 'uptime_24h', 'response_time_24h']]);
    }

    /** @param array<string, mixed> $data */
    private function checkWindow(array $data, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if (! $this->timestamp($data['from'] ?? null, 'UptimeRobot')->equalTo($start)
            || ! $this->timestamp($data['to'] ?? null, 'UptimeRobot')->equalTo($end)) {
            throw new ConnectorException('UptimeRobot statistics cover a different observation window.');
        }
    }
}
