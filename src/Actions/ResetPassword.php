<?php

namespace Metribay\Core\Actions;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetPassword implements ResetsUserPasswords
{
    /** @param array<string, mixed> $input */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, ['password' => ['required', 'string', Password::min(14), 'confirmed']])->validate();
        $user->forceFill(['password' => $input['password'], 'remember_token' => Str::random(60)])->save();
    }
}
