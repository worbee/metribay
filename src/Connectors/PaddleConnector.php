<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use NumberFormatter;

final class PaddleConnector extends AbstractConnector
{
    private const METRICS = [
        'paddle_revenue' => '/metrics/revenue',
        'paddle_mrr' => '/metrics/monthly-recurring-revenue',
        'paddle_active_subscribers' => '/metrics/active-subscribers',
    ];

    private const KEY_PATTERN = '/^pdl_(?:live|sdbx)_apikey_[a-zA-Z0-9_-]{1,200}$/D';

    public const ACCOUNT_PATTERN = '/^slr_[a-z0-9]{1,64}$/D';

    /** @param array<string, mixed> $credentials */
    public function accountId(array $credentials): string
    {
        $data = $this->get('/', $credentials);
        $caller = $this->object($data['caller'] ?? null, 'Paddle');
        $account = $caller['account']['id'] ?? null;
        if (($caller['auth_type'] ?? null) !== 'api_key' || ($caller['permissions'] ?? null) !== ['metrics.read']
            || ! is_string($account) || ! preg_match(self::ACCOUNT_PATTERN, $account)) {
            throw new ConnectorException('Use a dedicated Paddle API key with only Metrics (Read).');
        }

        return $account;
    }

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'Paddle');
        if ($this->accountId($credentials) !== ($source->config['paddle_account'] ?? null)) {
            throw new ConnectorException('The Paddle API key belongs to a different account.');
        }
        [$start, $last] = $this->window($source, $now->utc()->subDay(), defaultDays: 180);
        $end = $last->addDay();
        $daily = [];
        foreach (self::METRICS as $metric => $path) {
            $data = $this->get($path, $credentials, ['from' => $start->toDateString(), 'to' => $end->toDateString()]);
            if (($data['interval'] ?? null) !== 'day') {
                throw new ConnectorException('Paddle returned an unexpected reporting interval.');
            }
            $updated = $this->timestamp($data['updated_at'] ?? null, 'Paddle')->utc()->toDateString();
            $currency = $metric === 'paddle_active_subscribers' ? null : $this->currency($data['currency_code'] ?? null);
            $scale = 1;
            if ($currency !== null) {
                $formatter = new NumberFormatter('en', NumberFormatter::CURRENCY);
                $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);
                $scale = 10 ** $formatter->getAttribute(NumberFormatter::FRACTION_DIGITS);
            }
            $seen = [];
            foreach ($this->list($data['timeseries'] ?? null, 'Paddle') as $item) {
                $row = $this->object($item, 'Paddle');
                $date = $this->timestamp($row['timestamp'] ?? null, 'Paddle')->utc()->toDateString();
                if ($date < $start->toDateString() || $date >= $end->toDateString() || $date > $updated || isset($seen[$date])) {
                    throw new ConnectorException('Paddle returned an unexpected reporting date.');
                }
                $seen[$date] = true;
                $point = $this->daily($metric, $date, $this->count($row[$currency === null ? 'count' : 'amount'] ?? null, 'Paddle') / $scale, $date === $updated);
                if ($currency !== null) {
                    $point['context'] = ['currency' => $currency];
                }
                $daily[] = $point;
            }
        }

        return new CollectionResult(daily: $daily);
    }

    private function currency(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^[A-Z]{3}$/D', $value)) {
            throw new ConnectorException('Paddle returned an invalid currency.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $credentials, array $query = []): array
    {
        $key = $this->text($credentials['api_key'] ?? null, 'Paddle API key', 220);
        if (! preg_match(self::KEY_PATTERN, $key)) {
            throw new ConnectorException('Use a Paddle Billing API key.');
        }
        $host = str_starts_with($key, 'pdl_sdbx_') ? 'sandbox-api.paddle.com' : 'api.paddle.com';
        $response = $this->http->request('GET', 'https://'.$host.$path, [
            'headers' => ['Authorization' => 'Bearer '.$key, 'Paddle-Version' => '1'],
            'query' => $query,
        ], timeout: 8);

        return $this->object($this->json($response, 'Paddle')['data'] ?? null, 'Paddle');
    }
}
