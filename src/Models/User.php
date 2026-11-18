<?php

namespace Metribay\Core\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Metribay\Core\Services\Locales;

class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    use Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'two_factor_confirmed_at' => 'datetime'];
    }

    /** Notifications and queued mail use the user's chosen language. */
    public function preferredLocale(): ?string
    {
        return Locales::supported($this->locale) ? $this->locale : null;
    }
}
