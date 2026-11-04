<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Illuminate\Http\Client\Response;

/** Bearer token stays in the Authorization header, never in the URL. */
final class PlausibleClient
{
    public function __construct(private readonly SafeHttpClient $http) {}

    /** @param array<string, mixed> $options */
    public function request(string $method, string $base, string $apiKey, string $path, array $options = []): Response
    {
        $headers = $options['headers'] ?? [];
        if (! is_array($headers)) {
            throw new ConnectorException('Invalid connector request headers.');
        }
        $options['headers'] = ['Authorization' => 'Bearer '.$apiKey] + $headers;

        return $this->http->request($method, rtrim($base, '/').$path, $options);
    }
}
