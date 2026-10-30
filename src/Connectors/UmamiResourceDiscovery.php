<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/** Personal and team websites, including older Umami versions without includeTeams. */
final class UmamiResourceDiscovery extends AbstractConnector
{
    /** @param array<string,mixed> $credentials
     * @return list<array{id:string,name:string,domain:string,timezone:string}>
     */
    public function discover(array $credentials): array
    {
        $base = rtrim((string) ($credentials['base_url'] ?? ''), '/');
        $requests = 0;
        $request = function (string $method, string $path, array $options = []) use ($base, &$requests): array {
            if (++$requests > 40) {
                throw new UmamiDiscoveryException(__('Umami has too many resources to list in one request. Enter the Website UUID manually.'));
            }
            try {
                $response = $this->http->request($method, $base.$path, $options);
            } catch (ProviderRequestLimit $exception) {
                throw $exception;
            } catch (ConnectorException $exception) {
                throw new UmamiDiscoveryException(__('Unable to connect to Umami.').' '.$exception->getMessage());
            }
            $status = $response->status();
            if ($status !== 200) {
                $message = match (true) {
                    $path === '/api/auth/login' && in_array($status, [400, 401, 403]) => __('Umami sign-in failed. Check the connected account’s username and password.'),
                    $status === 401 => __('Umami rejected the session. Reconnect the account and try again.'),
                    $status === 403 => __('Umami denied access. Check this account’s website and team permissions.'),
                    $status === 404 => __('Umami could not find the resource-list endpoint. Check the server URL and Umami version, or enter the Website UUID manually.'),
                    $status === 429 => __('Umami is rate limiting requests. Wait a moment, then refresh the list.'),
                    $status >= 500 => __('Umami is temporarily unavailable. Try again later.'),
                    default => __('Umami could not list resources. Check the server URL and account access.'),
                };
                throw new UmamiDiscoveryException($message.' (HTTP '.$status.')');
            }
            $data = $response->json();
            if (! is_array($data) || array_is_list($data)) {
                throw new UmamiDiscoveryException(__('Umami returned an invalid response. Check the server URL and Umami version.'));
            }

            return $data;
        };
        $login = $request('POST', '/api/auth/login', ['json' => ['username' => $credentials['username'] ?? '', 'password' => $credentials['password'] ?? '']]);
        if (! is_string($login['token'] ?? null) || $login['token'] === '') {
            throw new UmamiDiscoveryException(__('Umami sign-in did not return a session token. Check the server URL and Umami version.'));
        }
        $headers = ['Authorization' => 'Bearer '.$login['token']];
        $pages = function (string $path) use ($request, $headers): array {
            $rows = [];
            for ($page = 1; $page <= 20; $page++) {
                $data = $request('GET', $path, ['headers' => $headers, 'query' => ['page' => $page, 'pageSize' => 100]]);
                if (! is_array($data['data'] ?? null) || ! array_is_list($data['data'])
                    || ! is_int($data['count'] ?? null) || $data['count'] < 0) {
                    throw new UmamiDiscoveryException(__('Umami returned an invalid resource list. Check the Umami version or enter the Website UUID manually.'));
                }
                foreach ($data['data'] as $row) {
                    if (! is_array($row) || ! is_string($row['id'] ?? null) || $row['id'] === '') {
                        throw new UmamiDiscoveryException(__('Umami returned an invalid resource. Enter the Website UUID manually.'));
                    }
                    $rows[] = $row;
                }
                if (count($rows) >= $data['count']) {
                    return $rows;
                }
                if ($data['data'] === []) {
                    break;
                }
            }
            throw new UmamiDiscoveryException(__('Umami’s resource list could not be fully loaded. Enter the Website UUID manually.'));
        };
        $websites = $pages('/api/websites');
        foreach ($pages('/api/me/teams') as $team) {
            array_push($websites, ...$pages('/api/teams/'.rawurlencode($team['id']).'/websites'));
        }
        $out = [];
        foreach ($websites as $website) {
            $id = $website['id'];
            $out[$id] = ['id' => $id, 'name' => html_entity_decode(strip_tags((string) ($website['name'] ?? $id)), ENT_QUOTES), 'domain' => (string) ($website['domain'] ?? ''), 'timezone' => 'UTC'];
        }

        return array_values($out);
    }

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        throw new \LogicException('Discovery does not collect metrics.');
    }
}
