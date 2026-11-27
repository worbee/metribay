<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use NumberFormatter;

/**
 * Valid orders and their revenue per day from the PrestaShop Webservice. Valid orders are those in a state PrestaShop
 * counts as validated (the order's `valid` flag), as its own statistics do. Revenue is the order total without tax,
 * converted to the shop's default currency with the rate stored on each order. Order dates are in the shop's timezone,
 * which the source's reporting timezone must match. Refunds and credit slips are not subtracted.
 */
final class PrestaShopConnector extends AbstractConnector
{
    /** Enough for 400 000 orders in one window; a larger shop needs a shorter history window. */
    private const MAX_PAGES = 400;

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $base = PrestaShopClient::base($credentials['base_url'] ?? null);
        $key = PrestaShopClient::key($credentials['api_key'] ?? null);
        $shop = PrestaShopClient::shopId($source->config['shop_id'] ?? null);
        [$start, $today] = $this->window($source, $now, defaultDays: 180);
        $client = new PrestaShopClient($this->http);
        $currency = $this->defaultCurrency($client->get($base, $key, 'currencies', ['display' => '[id,iso_code,conversion_rate,active,deleted]'], $shop));

        $first = $start->toDateString();
        $last = $today->toDateString();
        $orders = [];
        $revenue = [];
        for ($page = 0; ; $page++) {
            if ($page >= self::MAX_PAGES) {
                throw new ConnectorException('This PrestaShop shop has too many orders for the history window. Choose a shorter history window.');
            }
            $data = $client->get($base, $key, 'orders', [
                'display' => '[id,date_add,total_paid_tax_excl,conversion_rate]',
                'filter[id_shop]' => '['.$shop.']',
                'filter[valid]' => '[1]',
                'filter[date_add]' => '['.$first.' 00:00:00,'.$last.' 23:59:59]',
                'date' => '1',
                'sort' => '[id_ASC]',
                'limit' => ($page * PrestaShopClient::PAGE_SIZE).','.PrestaShopClient::PAGE_SIZE,
            ], $shop);
            $rows = $data === [] ? [] : $this->list($data['orders'] ?? null, 'PrestaShop');
            if (count($rows) > PrestaShopClient::PAGE_SIZE) {
                throw new ConnectorException('PrestaShop returned more orders than requested.');
            }
            foreach ($rows as $row) {
                $order = $this->object($row, 'PrestaShop');
                $day = $this->orderDate($order['date_add'] ?? null);
                if ($day < $first || $day > $last) {
                    throw new ConnectorException('PrestaShop returned an order outside the requested dates.');
                }
                $rate = $this->number($order['conversion_rate'] ?? null, 'PrestaShop');
                if ($rate <= 0) {
                    throw new ConnectorException('PrestaShop returned an order without a currency rate.');
                }
                $orders[$day] = ($orders[$day] ?? 0) + 1;
                $revenue[$day] = ($revenue[$day] ?? 0.0) + $this->number($order['total_paid_tax_excl'] ?? null, 'PrestaShop') / $rate;
            }
            if (count($rows) < PrestaShopClient::PAGE_SIZE) {
                break;
            }
        }

        $formatter = new NumberFormatter('en', NumberFormatter::CURRENCY);
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);
        $digits = max(0, (int) $formatter->getAttribute(NumberFormatter::FRACTION_DIGITS));
        $daily = [];
        for ($date = $start; $date <= $today; $date = $date->addDay()) {
            $day = $date->toDateString();
            $partial = $day === $last;
            $daily[] = $this->daily('store_orders', $day, (float) ($orders[$day] ?? 0), $partial);
            $daily[] = $this->daily('store_revenue', $day, round($revenue[$day] ?? 0.0, $digits), $partial) + ['context' => ['currency' => $currency]];
        }

        return new CollectionResult(daily: $daily);
    }

    /**
     * The ISO code of the shop's default currency: the one currency PrestaShop keeps at rate 1. Deleted and inactive
     * currencies only decide when no active one has rate 1.
     *
     * @param  array<string, mixed>  $data
     */
    private function defaultCurrency(array $data): string
    {
        $candidates = [];
        foreach ($data === [] ? [] : $this->list($data['currencies'] ?? null, 'PrestaShop') as $row) {
            $currency = $this->object($row, 'PrestaShop');
            $code = $currency['iso_code'] ?? null;
            if (! is_string($code) || ! preg_match('/^[A-Za-z]{3}$/D', $code)) {
                throw new ConnectorException('PrestaShop returned an invalid currency.');
            }
            if (abs($this->number($currency['conversion_rate'] ?? null, 'PrestaShop') - 1.0) < 1e-9) {
                $current = (string) ($currency['active'] ?? '1') === '1' && (string) ($currency['deleted'] ?? '0') !== '1';
                $candidates[$current ? 'current' : 'other'][strtoupper($code)] = true;
            }
        }
        $codes = array_keys($candidates['current'] ?? $candidates['other'] ?? []);
        if (count($codes) !== 1) {
            throw new ConnectorException('Metribay could not tell the PrestaShop default currency: exactly one currency should have exchange rate 1. Check the currency rates in PrestaShop.');
        }

        return $codes[0];
    }

    /** Order dates are local shop times such as 2026-11-20 14:05:00. */
    private function orderDate(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^(\d{4}-\d\d-\d\d) (\d\d):(\d\d):(\d\d)$/D', $value, $parts)
            || (int) $parts[2] > 23 || (int) $parts[3] > 59 || (int) $parts[4] > 59) {
            throw new ConnectorException('PrestaShop returned an invalid order date.');
        }

        return $this->date($parts[1], 'PrestaShop');
    }
}
