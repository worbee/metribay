<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web', 'middleware' => ['web'], 'auth_middleware' => 'auth', 'passwords' => 'users', 'username' => 'email', 'email' => 'email', 'views' => true, 'home' => '/', 'prefix' => '', 'domain' => null, 'lowercase_usernames' => true,
    'redirects' => ['password-confirmation' => '/account'],
    'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'],
    'features' => array_values(array_filter([
        env('METRIBAY_EDITION', 'personal') === 'cloud' ? Features::registration() : null,
        Features::resetPasswords(), Features::emailVerification(), Features::updateProfileInformation(), Features::updatePasswords(),
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
    ])),
];
