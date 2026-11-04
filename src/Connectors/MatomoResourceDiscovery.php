<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/** Websites the auth token's user can at least view. */
final class MatomoResourceDiscovery extends AbstractConnector
{
    /** @param array<string,mixed> $credentials
     * @return list<array{id:string,name:string,domain:string,timezone:string}>
     */
    public function discover(array $credentials): array
    {
        $base = rtrim((string) ($credentials['base_url'] ?? ''), '/');
        $token = (string) ($credentials['token_auth'] ?? '');
        try {
            $response = (new MatomoClient($this->http))->call($base, $token, ['method' => 'SitesManager.getSitesWithAtLeastViewAccess']);
        } catch (ProviderRequestLimit $exception) {
            throw $exception;
        } catch (ConnectorException $exception) {
            throw new DiscoveryException(__('Unable to connect to Matomo.').' '.$exception->getMessage());
        }
        if ($response->status() !== 200) {
            $message = match ($response->status()) {
                401 => __('Matomo rejected the auth token. Create a new token and reconnect.'),
                403 => __('Matomo denied access. Check this token’s site permissions.'),
                default => __('Matomo could not list sites. Check the server URL and auth token, or enter the site ID manually.'),
            };
            throw new DiscoveryException($message.' (HTTP '.$response->status().')');
        }
        $data = $response->json();
        if (! is_array($data) || ($data['result'] ?? null) === 'error') {
            throw new DiscoveryException(__('Matomo returned an invalid site list. Enter the site ID manually.'));
        }
        $rows = array_is_list($data) ? $data : array_values($data);
        if (count($rows) >= 2000) {
            throw new DiscoveryException(__('Matomo has too many sites to list. Enter the site ID manually.'));
        }
        $out = [];
        foreach ($rows as $site) {
            if (! is_array($site) || (! is_int($site['idsite'] ?? null) && ! is_string($site['idsite'] ?? null))
                || ! preg_match('/^[1-9][0-9]{0,9}$/D', (string) $site['idsite'])
                || ! is_string($site['timezone'] ?? null) || ! in_array($site['timezone'], timezone_identifiers_list(), true)) {
                throw new DiscoveryException(__('Matomo returned an invalid site list. Enter the site ID manually.'));
            }
            $id = (string) $site['idsite'];
            $host = parse_url((string) ($site['main_url'] ?? ''), PHP_URL_HOST);
            $out[$id] = ['id' => $id, 'name' => html_entity_decode(strip_tags((string) ($site['name'] ?? $id)), ENT_QUOTES), 'domain' => is_string($host) ? $host : '', 'timezone' => $site['timezone']];
        }

        return array_values($out);
    }

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        throw new \LogicException('Discovery does not collect metrics.');
    }
}
