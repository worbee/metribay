<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/** A connector that can suggest which deployment environments become project events. */
interface ListsDeploymentEnvironments
{
    /**
     * @param  array<string, mixed>  $credentials
     * @return list<string>
     */
    public function environments(string $repository, array $credentials, CarbonImmutable $now): array;
}
