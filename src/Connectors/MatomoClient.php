<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Illuminate\Http\Client\Response;

/** token_auth stays in the POST body so POST-only tokens work and the URL can be logged. */
final class MatomoClient
{
    public function __construct(private readonly SafeHttpClient $http) {}

    /** @param array<string, string> $params */
    public function call(string $base, string $token, array $params): Response
    {
        $base = rtrim($base, '/');
        $endpoint = str_ends_with($base, '/index.php') ? $base : $base.'/index.php';

        return $this->http->request('POST', $endpoint, ['form_params' => [
            'module' => 'API', 'format' => 'JSON', 'format_metrics' => '0', 'token_auth' => $token,
        ] + $params]);
    }
}
