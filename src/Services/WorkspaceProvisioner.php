<?php

namespace Metribay\Core\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Contracts\Entitlements;
use Metribay\Core\Models\Workspace;

class WorkspaceProvisioner
{
    public function create(Authenticatable $user, string $name): Workspace
    {
        return DB::transaction(function () use ($user, $name) {
            DB::table('users')->where('id', $user->getAuthIdentifier())->lockForUpdate()->first();
            if (Workspace::query()->where('owner_id', $user->getAuthIdentifier())->count() >= app(Entitlements::class)->limit($user, 'workspaces')) {
                throw ValidationException::withMessages(['name' => 'Your plan does not include another workspace.']);
            }

            return Workspace::create(['owner_id' => $user->getAuthIdentifier(), 'name' => $name, 'slug' => substr(Str::slug($name) ?: 'workspace', 0, 70).'-'.Str::lower(Str::random(8))]);
        });
    }
}
