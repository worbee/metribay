<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

/**
 * PrestaShop Webservice (1.7, 8 and 9), read-only and in JSON. The key is sent as the HTTP Basic user name with an empty
 * password, never in the URL. Every request names the shop, so multistore keys read the configured shop's context.
 */
final class PrestaShopClient
{
    /** The webservice caps nothing itself; pages stay small enough for the response limit. */
    public const PAGE_SIZE = 1000;

    public function __construct(private readonly SafeHttpClient $http) {}

    /** The shop's address without /api, so the setting reads the same as the shop in a browser. */
    public static function base(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '' || strlen($value) > 500 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new ConnectorException('A valid PrestaShop shop URL is required.');
        }
        $parts = parse_url(trim($value));
        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || ($parts['port'] ?? 443) !== 443 || str_contains($parts['path'] ?? '', '..')) {
            throw new ConnectorException('Use the public HTTPS address of the shop, without a query or fragment.');
        }
        $path = rtrim($parts['path'] ?? '', '/');
        if (preg_match('~/api$~i', $path)) {
            $path = substr($path, 0, -4);
        }

        return 'https://'.strtolower($parts['host']).$path;
    }

    /** PrestaShop generates 32-character keys; a colon or whitespace would break HTTP Basic. */
    public static function key(mixed $value): string
    {
        $key = is_string($value) ? trim($value) : '';
        if (! preg_match('/^[\x21-\x39\x3B-\x7E]{32}$/D', $key)) {
            throw new ConnectorException('Use the 32-character PrestaShop webservice key.');
        }

        return $key;
    }

    public static function shopId(mixed $value): string
    {
        $id = is_int($value) ? (string) $value : $value;
        if (! is_string($id) || ! preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
            throw new ConnectorException('Use the numeric PrestaShop shop ID.');
        }

        return $id;
    }

    /**
     * Decoded JSON of a GET on /api/{resource}. An empty list comes back as [] from PrestaShop.
     *
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    public function get(string $base, string $key, string $resource, array $query = [], ?string $shopId = null): array
    {
        $response = $this->http->request('GET', $base.'/api/'.$resource, [
            'headers' => ['Authorization' => 'Basic '.base64_encode($key.':'), 'Output-Format' => 'JSON'],
            'query' => ['output_format' => 'JSON'] + ($shopId === null ? [] : ['id_shop' => $shopId]) + $query,
        ], 60);
        $status = $response->status();
        if ($status !== 200) {
            throw new ConnectorException(match (true) {
                $status === 401 => 'PrestaShop rejected the webservice key. Check that the key is active and allows GET on orders, currencies and shops.',
                $status === 403 => 'This PrestaShop webservice key may not read this shop. Allow the shop in the key’s settings.',
                $status === 404 => 'PrestaShop did not answer at /api. Turn on the webservice and friendly URLs, and use the shop’s own address.',
                $status === 503 => 'The PrestaShop shop is in maintenance mode or unavailable. Try again later.',
                default => "PrestaShop returned HTTP {$status}. Check the shop URL and webservice settings.",
            });
        }
        try {
            $data = json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new ConnectorException('PrestaShop did not return JSON. Check that the address is the shop’s own address and that the webservice is on.');
        }
        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new ConnectorException('PrestaShop returned an invalid response.');
        }

        return $data;
    }
}
