<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/** Sites a Stats API key can read. The same key lists them through the read-only Sites API. */
final class PlausibleResourceDiscovery extends AbstractConnector
{
    /** @param array<string,mixed> $credentials
     * @return list<array{id:string,name:string,domain:string,timezone:string}>
     */
    public function discover(array $credentials): array
    {
        $base = rtrim((string) ($credentials['base_url'] ?? ''), '/');
        $key = (string) ($credentials['api_key'] ?? '');
        $client = new PlausibleClient($this->http);
        $after = null;
        $out = [];
        for ($page = 1; $page <= 20; $page++) {
            $query = ['limit' => 100];
            if ($after !== null) {
                $query['after'] = $after;
            }
            try {
                $response = $client->request('GET', $base, $key, '/api/v1/sites', ['query' => $query]);
            } catch (ProviderRequestLimit $exception) {
                throw $exception;
            } catch (ConnectorException $exception) {
                throw new DiscoveryException(__('Unable to connect to Plausible.').' '.$exception->getMessage());
            }
            if ($response->status() !== 200) {
                $message = match ($response->status()) {
                    401 => __('Plausible rejected the API key. Create a new Stats API key and reconnect.'),
                    403 => __('Plausible denied access. Check that this Stats API key belongs to the team that owns the sites.'),
                    429 => __('Plausible is rate limiting requests. Wait a moment, then refresh the list.'),
                    default => __('Plausible could not list sites. Check the server URL and API key, or enter the site domain manually.'),
                };
                throw new DiscoveryException($message.' (HTTP '.$response->status().')');
            }
            $data = $response->json();
            if (! is_array($data) || ! is_array($data['sites'] ?? null) || ! array_is_list($data['sites'])) {
                throw new DiscoveryException(__('Plausible returned an invalid site list. Enter the site domain manually.'));
            }
            foreach ($data['sites'] as $site) {
                if (! is_array($site) || ! is_string($site['domain'] ?? null) || $site['domain'] === ''
                    || ! is_string($site['timezone'] ?? null) || ! in_array($site['timezone'], timezone_identifiers_list(), true)) {
                    throw new DiscoveryException(__('Plausible returned an invalid site list. Enter the site domain manually.'));
                }
                $domain = strtolower($site['domain']);
                $out[$domain] = ['id' => $domain, 'name' => $domain, 'domain' => $domain, 'timezone' => $site['timezone']];
            }
            $next = $data['meta']['after'] ?? null;
            if ($next === null) {
                return array_values($out);
            }
            if (! is_string($next) || ! preg_match('/^[A-Za-z0-9_=.-]{1,500}$/D', $next) || $next === $after) {
                throw new DiscoveryException(__('Plausible returned an invalid site list. Enter the site domain manually.'));
            }
            $after = $next;
        }

        throw new DiscoveryException(__('Plausible has too many sites to list. Enter the site domain manually.'));
    }

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        throw new \LogicException('Discovery does not collect metrics.');
    }
}
