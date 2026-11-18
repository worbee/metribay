<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/** Projects a personal API key can read in its current organization, and one project's timezone. */
final class PostHogResourceDiscovery extends AbstractConnector
{
    private const PAGE = 100;

    /** @param array<string,mixed> $credentials
     * @return list<array{id:string,name:string,domain:string,timezone:string}>
     */
    public function discover(array $credentials): array
    {
        [$client, $base, $key] = $this->client($credentials);
        $out = [];
        for ($page = 0; $page < 20; $page++) {
            try {
                $response = $client->request('GET', $base, $key, '/api/projects/', ['query' => ['limit' => self::PAGE, 'offset' => $page * self::PAGE]]);
            } catch (ProviderRequestLimit $exception) {
                throw $exception;
            } catch (ConnectorException $exception) {
                throw new DiscoveryException(__('Unable to connect to PostHog.').' '.$exception->getMessage());
            }
            if ($response->status() !== 200) {
                $message = match ($response->status()) {
                    401, 403 => __('PostHog rejected the personal API key. Check its scopes and organization access, then reconnect.'),
                    429 => __('PostHog is rate limiting requests. Wait a moment, then refresh the list.'),
                    default => __('PostHog could not list projects. Check the server URL and API key, or enter the project ID manually.'),
                };
                throw new DiscoveryException($message.' (HTTP '.$response->status().')');
            }
            $data = $response->json();
            if (! is_array($data) || ! is_array($data['results'] ?? null) || ! array_is_list($data['results'])) {
                throw new DiscoveryException(__('PostHog returned an invalid project list. Enter the project ID manually.'));
            }
            foreach ($data['results'] as $project) {
                try {
                    $id = PostHogClient::projectId($project['id'] ?? null);
                } catch (ConnectorException) {
                    throw new DiscoveryException(__('PostHog returned an invalid project list. Enter the project ID manually.'));
                }
                $name = is_string($project['name'] ?? null) && trim($project['name']) !== '' ? trim($project['name']) : $id;
                $timezone = is_string($project['timezone'] ?? null) && in_array($project['timezone'], timezone_identifiers_list(), true) ? $project['timezone'] : '';
                $out[$id] = ['id' => $id, 'name' => mb_substr($name, 0, 200), 'domain' => '', 'timezone' => $timezone];
            }
            if (count($data['results']) < self::PAGE || empty($data['next'])) {
                return array_values($out);
            }
        }

        throw new DiscoveryException(__('PostHog has too many projects to list. Enter the project ID manually.'));
    }

    /** @param array<string,mixed> $credentials */
    public function timezone(array $credentials, string $id): string
    {
        [$client, $base, $key] = $this->client($credentials);
        $id = PostHogClient::projectId($id);
        $project = $this->json($client->request('GET', $base, $key, '/api/projects/'.$id.'/'), 'PostHog');
        if ((string) ($project['id'] ?? '') !== $id) {
            throw new ConnectorException('PostHog returned a different project.');
        }
        $timezone = $this->text($project['timezone'] ?? null, 'PostHog project timezone', 64);
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new ConnectorException('Invalid PostHog project timezone.');
        }

        return $timezone;
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @return array{PostHogClient, string, string}
     */
    private function client(array $credentials): array
    {
        try {
            return [new PostHogClient($this->http), PostHogClient::base($credentials['base_url'] ?? null), $this->text($credentials['api_key'] ?? null, 'PostHog personal API key', 500)];
        } catch (ConnectorException $exception) {
            throw new DiscoveryException(__('Unable to connect to PostHog.').' '.$exception->getMessage());
        }
    }

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        throw new \LogicException('Discovery does not collect metrics.');
    }
}
