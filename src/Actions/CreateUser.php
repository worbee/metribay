<?php

namespace Metribay\Core\Actions;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Metribay\Core\Services\WorkspaceProvisioner;

class CreateUser implements CreatesNewUsers
{
    /** @param array<string, mixed> $input */
    public function create(array $input): User
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }
        Validator::make($input, ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:254', Rule::unique('users')], 'password' => ['required', 'string', Password::min(14), 'confirmed']])->validate();

        return DB::transaction(function () use ($input): User {
            $model = config('auth.providers.users.model');
            $user = $model::create(['name' => $input['name'], 'email' => mb_strtolower($input['email']), 'password' => $input['password']]);
            app(WorkspaceProvisioner::class)->create($user, 'My workspace');

            return $user;
        });
    }
}
