<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Metribay\Core\Contracts\GoogleAuthorization;

class OwnGoogleAuthorization implements GoogleAuthorization
{
    public function available(): bool
    {
        return config('metribay.google.enabled', true) && filled(config('metribay.google.client_id')) && filled(config('metribay.google.client_secret'));
    }

    public function label(): string
    {
        return 'Connect with your Google app';
    }

    public function client(): array
    {
        abort_unless($this->available(), 422, __('Configure your own Google OAuth app on this installation, or use your own credentials.'));

        return ['client_id' => (string) config('metribay.google.client_id'), 'client_secret' => (string) config('metribay.google.client_secret'), 'redirect_uri' => config('metribay.google.redirect_uri') ?: route('metribay.google.callback')];
    }

    public function credentials(string $refreshToken): array
    {
        $client = $this->client();

        return ProviderCatalog::credentials('ga4', ['client_id' => $client['client_id'], 'client_secret' => $client['client_secret'], 'refresh_token' => $refreshToken]);
    }
}
