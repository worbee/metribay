<?php

declare(strict_types=1);

namespace Metribay\Core\Contracts;

use Metribay\Core\Models\Connection;

interface ConnectionCredentials
{
    /** @return array<string, mixed> Runtime credentials, never rendered or exported. */
    public function resolve(Connection $connection): array;
}
