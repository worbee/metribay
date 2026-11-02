<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

/** Azure DevOps refused environment data: the personal access token lacks the Environment scope. Safe to display. */
final class AzureDevOpsDeploymentAccess extends DeploymentAccess
{
    public function __construct()
    {
        parent::__construct('Azure DevOps did not allow reading environments. Give the access token the Environment (Read & manage) scope, or clear the deployment environments.');
    }
}
