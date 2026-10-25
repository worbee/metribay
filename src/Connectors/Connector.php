<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

interface Connector
{
    /** @param array<string, mixed> $credentials */
    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult;
}
