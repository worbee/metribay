<?php

namespace Metribay\Core\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Metribay\Core\Contracts\Entitlements;

class PersonalEntitlements implements Entitlements
{
    public function allows(Authenticatable $user, string $feature): bool
    {
        return ! in_array($feature, ['teams', 'multiple_workspaces', 'billing'], true);
    }

    public function limit(Authenticatable $user, string $resource): int
    {
        return in_array($resource, ['workspaces', 'members'], true) ? 1 : PHP_INT_MAX;
    }
}
