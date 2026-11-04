<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

/** Public web addresses for RSS, Atom and sitemap documents. */
final class ContentUrl
{
    public static function normalize(mixed $url): string
    {
        $parts = self::parts($url, 500, ['https']);
        $host = strtolower($parts['host']);
        if (! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host)
            || preg_match('/\.(?:localhost|local|internal|test|invalid)$/D', $host)) {
            throw new ConnectorException('Use a public HTTPS address without credentials or a fragment.');
        }

        return 'https://'.$host.($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /** A page linked from a feed or sitemap. HTTP is allowed; the document Metribay fetches is still HTTPS. */
    public static function page(mixed $url): string
    {
        $parts = self::parts($url, 2000, ['http', 'https']);
        $host = strtolower($parts['host']);
        if ($host === '' || str_contains($host, '..')) {
            throw new ConnectorException('A published address is not a usable URL.');
        }

        return $parts['scheme'].'://'.$host.($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * @param  list<string>  $schemes
     * @return array<string, mixed>
     */
    private static function parts(mixed $url, int $max, array $schemes): array
    {
        if (! is_string($url) || trim($url) === '' || strlen($url) > $max || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new ConnectorException('Use a public HTTPS address without credentials or a fragment.');
        }
        $parts = parse_url(trim($url));
        if ($parts === false || ! in_array($parts['scheme'] ?? '', $schemes, true) || ($parts['port'] ?? 443) !== 443
            || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new ConnectorException('Use a public HTTPS address without credentials or a fragment.');
        }

        return $parts;
    }
}
