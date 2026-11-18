<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Illuminate\Http\Client\Response;

/**
 * PostHog private API with a personal API key. The key stays in the Authorization header, never in the URL.
 * Only the project, event definition and HogQL query endpoints are used, all read-only.
 */
final class PostHogClient
{
    public function __construct(private readonly SafeHttpClient $http) {}

    /**
     * The PostHog app host for API calls. The ingestion hosts (us.i.posthog.com) and the legacy app.posthog.com
     * do not serve the private API for every region, so they map to the regional app host.
     */
    public static function base(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '' || strlen($value) > 500 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new ConnectorException('A valid PostHog server URL is required.');
        }
        $base = rtrim(trim($value), '/');
        $parts = parse_url($base);
        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || ($parts['port'] ?? 443) !== 443 || str_contains($parts['path'] ?? '', '..')) {
            throw new ConnectorException('Use a public HTTPS PostHog server URL without a query or fragment.');
        }
        $host = strtolower($parts['host']);
        $regional = match ($host) {
            'us.i.posthog.com', 'app.posthog.com', 'us.posthog.com' => 'https://us.posthog.com',
            'eu.i.posthog.com', 'eu.posthog.com' => 'https://eu.posthog.com',
            default => null,
        };

        return $regional ?? 'https://'.$host.rtrim($parts['path'] ?? '', '/');
    }

    public static function projectId(mixed $value): string
    {
        $id = is_int($value) ? (string) $value : $value;
        if (! is_string($id) || ! preg_match('/^[1-9][0-9]{0,18}$/D', $id)) {
            throw new ConnectorException('Use the numeric PostHog project ID.');
        }

        return $id;
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $base, string $apiKey, string $path, array $options = []): Response
    {
        $headers = $options['headers'] ?? [];
        if (! is_array($headers)) {
            throw new ConnectorException('Invalid connector request headers.');
        }
        $options['headers'] = ['Authorization' => 'Bearer '.$apiKey] + $headers;

        return $this->http->request($method, $base.$path, $options, 60);
    }
}
