<?php

namespace Metribay\Core\Actions;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateProfile implements UpdatesUserProfileInformation
{
    /** @param array<string, mixed> $input */
    public function update(User $user, array $input): void
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }
        Validator::make($input, ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($user->id)]])->validateWithBag('updateProfileInformation');
        $changed = mb_strtolower($input['email']) !== $user->email;
        $user->forceFill(['name' => $input['name'], 'email' => mb_strtolower($input['email'])]);
        if ($changed) {
            $user->email_verified_at = null;
        }
        $user->save();
        if ($changed) {
            $user->sendEmailVerificationNotification();
        }
    }
}
