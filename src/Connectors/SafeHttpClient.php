<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Closure;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Contracts\ProviderRequests;
use Psr\Http\Message\ResponseInterface;

/** Public HTTPS only. Revalidated DNS is pinned at socket creation, with TLS hostname verification. */
final class SafeHttpClient
{
    private readonly Closure $resolver;

    /** @param null|callable(string): list<string> $resolver Injectable only to make fixtures independent of DNS. */
    public function __construct(?callable $resolver = null, private readonly int $maxBytes = 8_388_608)
    {
        $this->resolver = $resolver === null ? self::resolve(...) : Closure::fromCallable($resolver);
        if ($maxBytes < 1 || $maxBytes > 33_554_432) {
            throw new \InvalidArgumentException('Invalid connector response size limit.');
        }
    }

    /**
     * @param  array<string, mixed>  $options  Only payload/query/header options, never transport configuration.
     */
    public function request(string $method, string $url, array $options = []): Response
    {
        app(ProviderRequests::class)->authorize();
        $method = strtoupper($method);
        if (! in_array($method, ['GET', 'POST'], true)
            || array_diff(array_keys($options), ['query', 'json', 'form_params', 'headers']) !== []) {
            throw new ConnectorException('Unsupported connector request options.');
        }
        if (! extension_loaded('curl')) {
            throw new ConnectorException('The PHP cURL extension is required for secure provider connections.');
        }
        $host = $this->hostname($url);
        try {
            $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolver)($host);
        } catch (\Throwable) {
            throw new ConnectorException('The provider hostname could not be resolved.');
        }
        if ($addresses === []) {
            throw new ConnectorException('The provider hostname could not be resolved.');
        }
        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                throw new ConnectorException('Provider endpoints must resolve exclusively to public IP addresses.');
            }
        }
        $address = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
        $headers = $options['headers'] ?? [];
        if (! is_array($headers)) {
            throw new ConnectorException('Invalid connector request headers.');
        }
        foreach ($headers as $name => $value) {
            if (! is_string($name) || ! preg_match('/^[A-Za-z0-9-]+$/D', $name)
                || in_array(strtolower($name), ['host', 'connection', 'proxy-authorization', 'transfer-encoding', 'content-length'], true)
                || ! is_string($value) || preg_match('/[\r\n\x00]/', $value)) {
                throw new ConnectorException('Invalid connector request headers.');
            }
        }
        $options['headers'] = $headers + ['User-Agent' => 'Metribay/0.1 (+https://metribay.com)', 'Accept' => 'application/json'];
        $sink = new BoundedResponseStream(Utils::streamFor(fopen('php://temp/maxmemory:1048576', 'w+')), $this->maxBytes);
        $transport = [
            'allow_redirects' => false,
            'protocols' => ['https'],
            'verify' => true,
            'proxy' => '',
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
            'sink' => $sink,
            'curl' => [
                CURLOPT_RESOLVE => filter_var($host, FILTER_VALIDATE_IP) === false ? ["{$host}:443:{$address}"] : [],
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE => true,
            ],
            'on_headers' => function (ResponseInterface $response): void {
                $length = $response->getHeaderLine('Content-Length');
                if ($length !== '' && (! ctype_digit($length) || (float) $length > $this->maxBytes)) {
                    throw new ConnectorException('Provider response exceeded the size limit.');
                }
            },
        ];
        try {
            // Explicit cURL handler is essential: PHP streams ignore CURLOPT_RESOLVE.
            // setHandler retains Laravel's fake and recorder middleware for fixture tests.
            $pending = Http::withOptions($transport)->setHandler(new CurlHandler);
            if (isset($options['form_params'])) {
                $pending->asForm();
            }
            $response = $pending->send($method, $url, $options);
            if (strlen($response->body()) > $this->maxBytes) {
                throw new ConnectorException('Provider response exceeded the size limit.');
            }
            if ($response->redirect()) {
                throw new ConnectorException('Provider redirects are not allowed. Use the final HTTPS endpoint.');
            }

            return $response;
        } catch (ConnectorException $exception) {
            throw $exception;
        } catch (\Throwable) {
            // Guzzle exceptions can contain URL credentials (Bing) or echoed request bodies.
            throw new ConnectorException('The provider request failed. Check connectivity and provider limits.');
        }
    }

    private function hostname(string $url): string
    {
        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url)) {
            throw new ConnectorException('Use a public HTTPS endpoint on port 443.');
        }
        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host'])
            || ($parts['port'] ?? 443) !== 443 || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new ConnectorException('Use a public HTTPS endpoint on port 443 without URL credentials.');
        }
        $host = strtolower(trim($parts['host'], '[]'));
        if (filter_var($host, FILTER_VALIDATE_IP) === false
            && (! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host)
                || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal'))) {
            throw new ConnectorException('Use a public provider hostname.');
        }

        return $host;
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            return [];
        }
        $addresses = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            } elseif (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }

    public static function isPublicAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        $packed = inet_pton($address);
        if ($packed === false) {
            return false;
        }
        if (strlen($packed) === 4) {
            $blocked = ['0.0.0.0/8', '100.64.0.0/10', '169.254.0.0/16', '192.0.0.0/24', '192.0.2.0/24',
                '192.88.99.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'];
        } else {
            // Global unicast only. Exclude transition, documentation and special-use assignments.
            if (! self::inCidr($packed, '2000::/3')) {
                return false;
            }
            $blocked = ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20'];
        }
        foreach ($blocked as $cidr) {
            if (self::inCidr($packed, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private static function inCidr(string $packed, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $prefix = inet_pton($network);
        if ($prefix === false || strlen($packed) !== strlen($prefix)) {
            return false;
        }
        $bytes = intdiv((int) $bits, 8);
        $remainder = (int) $bits % 8;
        if (substr($packed, 0, $bytes) !== substr($prefix, 0, $bytes)) {
            return false;
        }

        return $remainder === 0 || ((ord($packed[$bytes]) ^ ord($prefix[$bytes])) & (255 << (8 - $remainder))) === 0;
    }
}
