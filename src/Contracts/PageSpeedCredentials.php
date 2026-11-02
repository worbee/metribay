<?php

declare(strict_types=1);

namespace Metribay\Core\Contracts;

/** Host-owned PageSpeed access. Core and Desktop use a connected account with their own key. */
interface PageSpeedCredentials
{
    public function managed(): bool;

    public function available(): bool;

    /** @return array{api_key: string} */
    public function resolve(): array;
}
