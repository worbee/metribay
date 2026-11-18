<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

final class GitHubRepositoryUnavailable extends ConnectorException
{
    public function __construct()
    {
        parent::__construct('GitHub repository unavailable. Check the repository name and give the connected account access.');
    }
}
