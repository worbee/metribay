<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

/** Withdraws an OAuth grant at Google. Best effort: callers report a failure instead of blocking on it. */
final readonly class GoogleRevocation
{
    public function __construct(private SafeHttpClient $http) {}

    /** @param array<string, mixed> $credentials Resolved runtime credentials of a Google connection. */
    public static function revocable(array $credentials): bool
    {
        return is_string($credentials['refresh_token'] ?? null) && trim($credentials['refresh_token']) !== '';
    }

    /**
     * Google withdraws the whole grant of that Google login for this OAuth app, not only this token.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function revoke(array $credentials): bool
    {
        if (! self::revocable($credentials)) {
            return false;
        }
        try {
            $response = $this->http->request('POST', 'https://oauth2.googleapis.com/revoke', ['form_params' => ['token' => $credentials['refresh_token']]], 15);
        } catch (\Throwable) {
            return false;
        }

        // invalid_token: already revoked or expired, so the grant is gone either way.
        return $response->successful() || ($response->status() === 400 && $response->json('error') === 'invalid_token');
    }
}
