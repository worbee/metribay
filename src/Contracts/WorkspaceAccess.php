<?php

namespace Metribay\Core\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Metribay\Core\Models\Workspace;

interface WorkspaceAccess
{
    /** @return Builder<Workspace> */
    public function queryFor(Authenticatable $user): Builder;

    public function allows(Authenticatable $user, Workspace $workspace, string $ability): bool;
}
