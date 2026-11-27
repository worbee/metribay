<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/**
 * Shops the webservice key can list. The shop timezone is not read: it lives in the configuration table, and a key
 * that may read configuration could also read stored secrets. The person adding the source enters it instead.
 */
final class PrestaShopResourceDiscovery extends AbstractConnector
{
    /** @param array<string,mixed> $credentials
     * @return list<array{id:string,name:string,domain:string,timezone:string}>
     */
    public function discover(array $credentials): array
    {
        try {
            $base = PrestaShopClient::base($credentials['base_url'] ?? null);
            $data = (new PrestaShopClient($this->http))->get($base, PrestaShopClient::key($credentials['api_key'] ?? null), 'shops', ['display' => '[id,name,active]', 'sort' => '[id_ASC]', 'limit' => '0,500']);
        } catch (ProviderRequestLimit $exception) {
            throw $exception;
        } catch (ConnectorException $exception) {
            throw new DiscoveryException(__('Unable to list PrestaShop shops.').' '.__($exception->getMessage()).' '.__('You can also enter the shop ID manually. A shop without multistore has ID 1.'));
        }
        $rows = $data['shops'] ?? ($data === [] ? [] : null);
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) >= 500) {
            throw new DiscoveryException(__('PrestaShop returned an invalid shop list. Enter the shop ID manually.'));
        }
        $host = (string) parse_url($base, PHP_URL_HOST);
        $out = [];
        foreach ($rows as $shop) {
            if (! is_array($shop)) {
                throw new DiscoveryException(__('PrestaShop returned an invalid shop list. Enter the shop ID manually.'));
            }
            try {
                $id = PrestaShopClient::shopId($shop['id'] ?? null);
            } catch (ConnectorException) {
                throw new DiscoveryException(__('PrestaShop returned an invalid shop list. Enter the shop ID manually.'));
            }
            if ((string) ($shop['active'] ?? '1') === '0') {
                continue;
            }
            $name = is_string($shop['name'] ?? null) ? trim(html_entity_decode(strip_tags($shop['name']), ENT_QUOTES)) : '';
            $out[] = ['id' => $id, 'name' => mb_substr($name !== '' ? $name : __('Shop :id', ['id' => $id]), 0, 120), 'domain' => count($rows) === 1 ? $host : $host.' · '.__('Shop ID :id', ['id' => $id]), 'timezone' => ''];
        }

        return $out;
    }

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        throw new \LogicException('Discovery does not collect metrics.');
    }
}
