<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

final readonly class ConnectorRegistry
{
    public function __construct(private SafeHttpClient $http) {}

    /** @return list<string> */
    public function kinds(): array
    {
        return ['umami', 'ga4', 'gsc', 'bing', 'wporg', 'github', 'uptimerobot', 'pagespeed', 'azure_devops', 'paddle'];
    }

    /** The GitHub connector, which also lists deployment environments. */
    public function github(): GitHubConnector
    {
        return new GitHubConnector($this->http);
    }

    /** The connector that lists deployment environments for this provider, if it imports deployments. */
    public function environments(string $kind): ListsDeploymentEnvironments
    {
        return match ($kind) {
            'github' => $this->github(),
            'azure_devops' => new AzureDevOpsConnector($this->http),
            default => throw new ConnectorException('This provider does not import deployments.'),
        };
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
            'paddle' => new PaddleConnector($this->http),
            'azure_devops' => new AzureDevOpsConnector($this->http),
            default => throw new ConnectorException('Unsupported source provider.'),
        };
    }
}
