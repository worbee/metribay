<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

final readonly class ConnectorRegistry
{
    public function __construct(private SafeHttpClient $http) {}

    /** @return list<string> */
    public function kinds(): array
    {
        return ['umami', 'ga4', 'gsc', 'bing', 'wporg', 'github', 'uptimerobot', 'pagespeed'];
    }

    public function for(string $kind): Connector
    {
        return match ($kind) {
            'umami' => new UmamiConnector($this->http),
            'ga4' => new Ga4Connector($this->http),
            'gsc' => new GscConnector($this->http),
            'bing' => new BingConnector($this->http),
            'wporg' => new WpOrgConnector($this->http),
            'github' => new GitHubConnector($this->http),
            'uptimerobot' => new UptimeRobotConnector($this->http),
            'pagespeed' => new PageSpeedConnector($this->http),
            default => throw new ConnectorException('Unsupported source provider.'),
        };
    }
}
