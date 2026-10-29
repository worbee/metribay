<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Contracts\ConnectionCredentials;
use Metribay\Core\Models\Connection;

class OwnConnectionCredentials implements ConnectionCredentials
{
    public function resolve(Connection $connection): array
    {
        $values = $connection->reveal();
        if (array_key_exists('managed_app', $values)) {
            throw new ConnectorException('Managed connections require the service that issued them. Connect your own account credentials on this installation.');
        }

        return $values;
    }
}
