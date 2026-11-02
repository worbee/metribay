<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Contracts\PageSpeedCredentials;

final class OwnPageSpeedCredentials implements PageSpeedCredentials
{
    public function managed(): bool
    {
        return false;
    }

    public function available(): bool
    {
        return false;
    }

    public function resolve(): array
    {
        throw new ConnectorException('Connect your own Google API key to collect page speed data.');
    }
}
