<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

/** GitHub refused deployment data: the account, token or app lacks read access to deployments. Safe to display. */
final class GitHubDeploymentAccess extends DeploymentAccess
{
    public function __construct()
    {
        parent::__construct('GitHub did not allow reading deployments. Allow read access to deployments for this connected account, or clear the deployment environments.');
    }
}
