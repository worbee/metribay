<?php

namespace Metribay\Core\Services;

use Illuminate\Validation\ValidationException;

final class ProjectConfiguration
{
    public static function domain(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $domain = rtrim(preg_replace('~^https?://~i', '', $value), '/');
        if (! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || str_contains($domain, '/')) {
            throw ValidationException::withMessages(['domain' => 'Use a hostname such as example.com.']);
        }

        return mb_strtolower($domain);
    }
}
