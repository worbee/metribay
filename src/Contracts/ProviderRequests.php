<?php

declare(strict_types=1);

namespace Metribay\Core\Contracts;

interface ProviderRequests
{
    /** Called before every outbound provider request, including token exchange and pagination. */
    public function authorize(): void;
}
