<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/** Bounded, read-only discovery through the same pinned transport as collection. */
final class ResourceDiscovery extends AbstractConnector
{
    /** @param array<string,mixed> $credentials
     * @return list<array{id:string,name:string,domain:string,timezone:string}>
     */
    public function discover(string $kind, array $credentials, string $search = ''): array
    {
        $base = rtrim((string) ($credentials['base_url'] ?? ''), '/');
        $headers = [];
        if ($kind === 'umami') {
            $login = $this->json($this->http->request('POST', $base.'/api/auth/login', ['json' => ['username' => $credentials['username'] ?? '', 'password' => $credentials['password'] ?? '']]), 'Umami');
            $headers = ['Authorization' => 'Bearer '.$this->text($login['token'] ?? null, 'Umami token')];
        } elseif (in_array($kind, ['ga4', 'gsc'], true)) {
            $scope = $kind === 'ga4' ? 'https://www.googleapis.com/auth/analytics.readonly' : 'https://www.googleapis.com/auth/webmasters.readonly';
            $headers = ['Authorization' => 'Bearer '.(new GoogleToken($this->http))->fetch($credentials, $scope)];
        } elseif ($kind === 'github') {
            if (! isset($credentials['token'])) {
                throw new ConnectorException('Use manual repository entry for this GitHub App connection.');
            }
            $headers = ['Authorization' => 'Bearer '.$credentials['token'], 'X-GitHub-Api-Version' => GitHubConnector::API_VERSION];
        }
        $out = [];
        $cursor = '';
        for ($page = 1; $page <= 20; $page++) {
            $options = ['headers' => $headers];
            $url = match ($kind) {
                'umami' => $base.'/api/websites',
                'ga4' => 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries',
                'gsc' => 'https://www.googleapis.com/webmasters/v3/sites',
                'github' => 'https://api.github.com/user/repos',
                'bing' => 'https://ssl.bing.com/webmaster/api.svc/json/GetUserSites',
                'uptimerobot' => 'https://api.uptimerobot.com/v2/getMonitors',
                'wporg' => 'https://api.wordpress.org/plugins/info/1.2/',
                default => throw new ConnectorException('Unsupported provider.'),
            };
            $options['query'] = match ($kind) {
                'umami' => ['page' => $page, 'pageSize' => 100],
                'ga4' => ['pageSize' => 200, 'pageToken' => $cursor],
                'github' => ['per_page' => 100, 'page' => $page, 'sort' => 'full_name'],
                'bing' => ['apikey' => $credentials['api_key'] ?? ''],
                'wporg' => ['action' => 'query_plugins', 'request' => ['search' => $search, 'per_page' => 30]],
                default => [],
            };
            $method = 'GET';
            if ($kind === 'uptimerobot') {
                $method = 'POST';
                $options['form_params'] = ['api_key' => $credentials['api_key'] ?? '', 'format' => 'json', 'offset' => ($page - 1) * 50, 'limit' => 50];
            }
            $response = $this->http->request($method, $url, $options);
            if ($response->status() !== 200) {
                throw new ConnectorException('Could not list resources. Check this account’s permissions or try again later.');
            }
            $data = $response->json();
            if (! is_array($data)) {
                throw new ConnectorException('The provider returned an invalid resource list.');
            }
            if (($kind === 'uptimerobot' && ($data['stat'] ?? '') !== 'ok') || ($kind === 'bing' && ! isset($data['d']))) {
                throw new ConnectorException('The provider could not confirm account access.');
            }
            $rows = match ($kind) {
                'umami' => $data['data'] ?? [], 'ga4' => $data['accountSummaries'] ?? [],
                'gsc' => $data['siteEntry'] ?? [], 'github' => $data, 'bing' => $data['d'] ?? [],
                'uptimerobot' => $data['monitors'] ?? [], 'wporg' => $data['plugins'] ?? [],
            };
            if (! is_array($rows) || ! array_is_list($rows)) {
                throw new ConnectorException('The provider returned an invalid resource list.');
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new ConnectorException('The provider returned an invalid resource.');
                }
                if ($kind === 'ga4') {
                    foreach ($row['propertySummaries'] ?? [] as $property) {
                        $id = basename($property['property']);
                        $out[] = ['id' => $id, 'name' => (string) $property['displayName'], 'domain' => '', 'timezone' => ''];
                        if (count($out) >= 500) {
                            return $out;
                        }
                    }

                    continue;
                }
                $id = (string) match ($kind) {
                    'umami' => $row['id'] ?? '', 'gsc' => $row['siteUrl'] ?? '', 'github' => $row['full_name'] ?? '',
                    'bing' => $row['Url'] ?? '', 'uptimerobot' => $row['id'] ?? '', 'wporg' => $row['slug'] ?? '',
                };
                if ($id === '') {
                    continue;
                }
                $domain = (string) ($row['domain'] ?? '');
                if (in_array($kind, ['gsc', 'bing'], true)) {
                    $domain = str_starts_with($id, 'sc-domain:') ? substr($id, 10) : (parse_url($id, PHP_URL_HOST) ?: '');
                }
                $out[] = ['id' => $id, 'name' => html_entity_decode(strip_tags((string) ($row['name'] ?? $row['friendly_name'] ?? $id)), ENT_QUOTES), 'domain' => $domain, 'timezone' => $kind === 'gsc' ? 'America/Los_Angeles' : 'UTC'];
            }
            $cursor = (string) ($data['nextPageToken'] ?? '');
            $more = match ($kind) {
                'umami' => $page * 100 < (int) ($data['count'] ?? 0), 'ga4' => $cursor !== '',
                'github' => count($rows) === 100, 'uptimerobot' => $page * 50 < (int) ($data['pagination']['total'] ?? 0),
                default => false,
            };
            if (! $more || count($out) >= 2000) {
                break;
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $credentials */
    public function googleTimezone(array $credentials, string $property): string
    {
        if (! ctype_digit($property)) {
            throw new ConnectorException('Invalid Google property.');
        }
        $token = (new GoogleToken($this->http))->fetch($credentials, 'https://www.googleapis.com/auth/analytics.readonly');
        $meta = $this->json($this->http->request('GET', 'https://analyticsadmin.googleapis.com/v1beta/properties/'.$property, ['headers' => ['Authorization' => 'Bearer '.$token]]), 'Google Analytics');
        $timezone = $this->text($meta['timeZone'] ?? null, 'property timezone');
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new ConnectorException('Invalid property timezone.');
        }

        return $timezone;
    }

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        throw new \LogicException('Discovery does not collect metrics.');
    }
}
