<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/** Bounded, read-only discovery through the same pinned transport as collection. */
class ResourceDiscovery extends AbstractConnector
{
    /** @param array<string,mixed> $credentials
     * @return list<array{id:string,name:string,domain:string,timezone:string}>
     */
    public function discover(string $kind, array $credentials, string $search = ''): array
    {
        if ($kind === 'umami') {
            return (new UmamiResourceDiscovery($this->http))->discover($credentials);
        }
        if ($kind === 'plausible') {
            return (new PlausibleResourceDiscovery($this->http))->discover($credentials);
        }
        if ($kind === 'matomo') {
            return (new MatomoResourceDiscovery($this->http))->discover($credentials);
        }
        if ($kind === 'azure_devops') {
            return $this->azureDevOps($credentials);
        }
        $headers = [];
        if (in_array($kind, ['ga4', 'gsc'], true)) {
            $scope = $kind === 'ga4' ? 'https://www.googleapis.com/auth/analytics.readonly' : 'https://www.googleapis.com/auth/webmasters.readonly';
            $headers = ['Authorization' => 'Bearer '.(new GoogleToken($this->http))->fetch($credentials, $scope)];
        } elseif (in_array($kind, ['github', 'github_releases'], true)) {
            if (! isset($credentials['token'])) {
                throw new ConnectorException($kind === 'github' ? 'Use manual repository entry for this GitHub App connection.' : 'Add a personal access token before listing repositories.');
            }
            $headers = ['Authorization' => 'Bearer '.$credentials['token'], 'Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => GitHubConnector::API_VERSION];
        }
        $out = [];
        $cursor = '';
        for ($page = 1; $page <= 20; $page++) {
            $options = ['headers' => $headers];
            $url = match ($kind) {
                'ga4' => 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries',
                'gsc' => 'https://www.googleapis.com/webmasters/v3/sites',
                'github', 'github_releases' => 'https://api.github.com/user/repos',
                'bing' => 'https://ssl.bing.com/webmaster/api.svc/json/GetUserSites',
                'uptimerobot' => 'https://api.uptimerobot.com/v2/getMonitors',
                'wporg' => 'https://api.wordpress.org/plugins/info/1.2/',
                default => throw new ConnectorException('Unsupported provider.'),
            };
            $options['query'] = match ($kind) {
                'ga4' => ['pageSize' => 200, 'pageToken' => $cursor],
                'github', 'github_releases' => ['per_page' => 100, 'page' => $page, 'sort' => 'full_name'],
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
                'ga4' => $data['accountSummaries'] ?? [],
                'gsc' => $data['siteEntry'] ?? [], 'github', 'github_releases' => $data, 'bing' => $data['d'] ?? [],
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
                    'gsc' => $row['siteUrl'] ?? '', 'github', 'github_releases' => $row['full_name'] ?? '',
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
                'ga4' => $cursor !== '',
                'github', 'github_releases' => count($rows) === 100, 'uptimerobot' => $page * 50 < (int) ($data['pagination']['total'] ?? 0),
                default => false,
            };
            if (! $more || count($out) >= 2000) {
                break;
            }
        }

        return $out;
    }

    /**
     * Enabled Git repositories in every project of the organization that the access token can read.
     *
     * @param  array<string,mixed>  $credentials
     * @return list<array{id:string,name:string,domain:string,timezone:string}>
     */
    private function azureDevOps(array $credentials): array
    {
        $organization = AzureDevOpsConnector::organization($credentials['organization'] ?? null);
        $token = $this->text($credentials['token'] ?? null, 'Azure DevOps personal access token', 1024);
        $response = $this->http->request('GET', 'https://dev.azure.com/'.$organization.'/_apis/git/repositories', [
            'headers' => ['Authorization' => 'Basic '.base64_encode(':'.$token), 'X-TFS-FedAuthRedirect' => 'Suppress'],
            'query' => ['api-version' => AzureDevOpsConnector::API_VERSION],
        ]);
        if ($response->status() !== 200) {
            throw new ConnectorException('Could not list resources. Check this account’s permissions or try again later.');
        }
        $out = [];
        foreach ($this->list($this->json($response, 'Azure DevOps')['value'] ?? null, 'Azure DevOps') as $row) {
            $row = $this->object($row, 'Azure DevOps');
            if (($row['isDisabled'] ?? false) === true) {
                continue;
            }
            $id = $this->text($this->object($row['project'] ?? null, 'Azure DevOps')['name'] ?? null, 'Azure DevOps project name', 64)
                .'/'.$this->text($row['name'] ?? null, 'Azure DevOps repository name', 64);
            $out[] = ['id' => $id, 'name' => $id, 'domain' => '', 'timezone' => 'UTC'];
            if (count($out) >= 2000) {
                break;
            }
        }
        usort($out, fn (array $a, array $b): int => strnatcasecmp($a['id'], $b['id']));

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
