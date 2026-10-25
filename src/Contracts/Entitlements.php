<?php

namespace Metribay\Core\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface Entitlements
{
    public function allows(Authenticatable $user, string $feature): bool;

    public function limit(Authenticatable $user, string $resource): int;
}
