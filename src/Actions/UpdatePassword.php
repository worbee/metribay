<?php

namespace Metribay\Core\Actions;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdatePassword implements UpdatesUserPasswords
{
    /** @param array<string, mixed> $input */
    public function update(User $user, array $input): void
    {
        Validator::make($input, ['current_password' => ['required', 'current_password:web'], 'password' => ['required', 'string', Password::min(14), 'confirmed']])->validateWithBag('updatePassword');
        $user->forceFill(['password' => $input['password']])->save();
    }
}
