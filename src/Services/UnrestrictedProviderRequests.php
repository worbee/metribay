<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Metribay\Core\Contracts\ProviderRequests;

class UnrestrictedProviderRequests implements ProviderRequests
{
    public function authorize(): void
    {
        // Self-hosted installations use their own provider accounts and quotas.
    }
}
