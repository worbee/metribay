<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\Credentials\UserRefreshCredentials;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** No file paths or configurable token endpoints: key material is decrypted by the host. */
final readonly class GoogleToken
{
    public function __construct(private SafeHttpClient $http) {}

    /** @param array<string, mixed> $credentials */
    public function fetch(array $credentials, string $scope): string
    {
        try {
            if (isset($credentials['refresh_token'])) {
                $auth = new UserRefreshCredentials($scope, [
                    'client_id' => $credentials['client_id'] ?? '',
                    'client_secret' => $credentials['client_secret'] ?? '',
                    'refresh_token' => $credentials['refresh_token'],
                ]);
            } else {
                $key = $credentials['service_account'] ?? $credentials['key_file'] ?? $credentials;
                if (is_string($key)) {
                    $key = json_decode($key, true, 16, JSON_THROW_ON_ERROR);
                }
                if (! is_array($key) || ! is_string($key['client_email'] ?? null)
                    || ! is_string($key['private_key'] ?? null)
                    || (($key['universe_domain'] ?? 'googleapis.com') !== 'googleapis.com')) {
                    throw new \InvalidArgumentException;
                }
                $auth = new ServiceAccountCredentials($scope, $key);
            }
            $token = $auth->fetchAuthToken(function (RequestInterface $request, array $unusedOptions = []): ResponseInterface {
                if ((string) $request->getUri() !== 'https://oauth2.googleapis.com/token' || $request->getMethod() !== 'POST') {
                    throw new ConnectorException('Unsupported Google token endpoint.');
                }
                parse_str((string) $request->getBody(), $form);
                $headers = [];
                foreach ($request->getHeaders() as $name => $values) {
                    if (in_array(strtolower($name), ['host', 'content-length'], true)) {
                        continue; // The pinned transport owns host and framing headers.
                    }
                    $headers[$name] = implode(', ', $values);
                }

                return $this->http->request('POST', (string) $request->getUri(), ['form_params' => $form, 'headers' => $headers])->toPsrResponse();
            });
            $value = $token['access_token'] ?? null;
            if (! is_string($value) || trim($value) === '' || preg_match('/[\r\n]/', $value)) {
                throw new \InvalidArgumentException;
            }

            return $value;
        } catch (\Throwable) {
            throw new ConnectorException('Google authentication failed. Check the service-account key or OAuth credentials and provider access.');
        }
    }
}
