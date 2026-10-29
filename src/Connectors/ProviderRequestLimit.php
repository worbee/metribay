<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

/** Credential-free host budget denial, safe to display and distinguish from auth errors. */
final class ProviderRequestLimit extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cloud provider request limit reached. Try again later. Scheduled imports will retry; your existing data is safe.');
    }
}
