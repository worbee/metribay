<?php

namespace Metribay\Core\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Models\Workspace;

class PersonalWorkspaceAccess implements WorkspaceAccess
{
    /** @return Builder<Workspace> */
    public function queryFor(Authenticatable $user): Builder
    {
        return Workspace::query()->where('owner_id', $user->getAuthIdentifier());
    }

    public function allows(Authenticatable $user, Workspace $workspace, string $ability): bool
    {
        return (string) $workspace->owner_id === (string) $user->getAuthIdentifier();
    }
}
