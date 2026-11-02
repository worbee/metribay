<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

final class PageSpeedUrl
{
    public static function normalize(mixed $url): string
    {
        if (! is_string($url) || strlen($url) > 500 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new ConnectorException('Use a public HTTPS page URL without credentials or a fragment.');
        }
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || ($parts['port'] ?? 443) !== 443
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host)
            || preg_match('/\.(?:localhost|local|internal|test|invalid)$/D', $host)) {
            throw new ConnectorException('Use a public HTTPS page URL without credentials or a fragment.');
        }

        return 'https://'.$host.($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    public static function origin(string $url): string
    {
        return 'https://'.parse_url(self::normalize($url), PHP_URL_HOST);
    }
}
