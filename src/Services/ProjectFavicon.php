<?php

namespace Metribay\Core\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Metribay\Core\Connectors\SafeHttpClient;

final class ProjectFavicon
{
    public function __construct(private readonly SafeHttpClient $http = new SafeHttpClient(maxBytes: 262_144)) {}

    /** @return array{body: string, type: string}|null */
    public function forDomain(string $domain): ?array
    {
        // Cache misses too. Never block dashboard rendering on a remote website.
        $key = 'metribay:favicon:v1:'.hash('sha256', $domain);
        $result = Cache::get($key);
        if (is_array($result)) {
            return $result['icon'];
        }
        $icon = $this->fetch($domain);
        Cache::put($key, ['icon' => $icon], $icon ? now()->addWeek() : now()->addDay());

        return $icon;
    }

    /** @return array{body: string, type: string}|null */
    private function fetch(string $domain): ?array
    {
        $base = 'https://'.$domain.'/';
        try {
            [$response] = $this->get($base.'favicon.ico', 'image/*');
            if ($response->successful() && ($icon = $this->image($response->body()))) {
                return $icon;
            }
            [$home, $base] = $this->get($base, 'text/html');
            if (! $home->successful()) {
                return null;
            }
            $document = new \DOMDocument;
            @$document->loadHTML($home->body(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $candidates = [];
            foreach ($document->getElementsByTagName('link') as $link) {
                $rel = preg_split('/\s+/', strtolower(trim($link->getAttribute('rel'))));
                if (! in_array('icon', $rel ?: [], true) || ! $link->hasAttribute('href')) {
                    continue;
                }
                $url = (string) UriResolver::resolve(new Uri($base), new Uri($link->getAttribute('href')));
                // Only the project's own host, always through public-IP-pinned HTTPS.
                if (parse_url($url, PHP_URL_HOST) === parse_url($base, PHP_URL_HOST) && ! in_array($url, $candidates, true)) {
                    $candidates[] = $url;
                }
            }
            foreach (array_slice($candidates, 0, 3) as $url) {
                [$response] = $this->get($url, 'image/*');
                if ($response->successful() && ($icon = $this->image($response->body()))) {
                    return $icon;
                }
            }
        } catch (\Throwable) {
            // No favicon (or unsafe/unreachable host) is a normal, cached result.
        }

        return null;
    }

    /** @return array{Response, string} */
    private function get(string $url, string $accept): array
    {
        for ($redirects = 0; $redirects <= 2; $redirects++) {
            $response = $this->http->request('GET', $url, ['headers' => ['Accept' => $accept]], 5, returnRedirect: true);
            if (! $response->redirect()) {
                return [$response, $url];
            }
            $location = $response->header('Location');
            if ($location === '') {
                break;
            }
            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
        }

        throw new \RuntimeException('Favicon redirect limit reached.');
    }

    /** @return array{body: string, type: string}|null */
    private function image(string $body): ?array
    {
        if (str_starts_with(ltrim($body), '<')) {
            $document = new \DOMDocument;
            if (@$document->loadXML($body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)
                && ! $document->doctype && $document->documentElement?->localName === 'svg'
                && $document->documentElement->namespaceURI === 'http://www.w3.org/2000/svg') {
                // Served with a script-free, network-free CSP sandbox, including on direct navigation.
                return ['body' => (string) $document->saveXML($document->documentElement), 'type' => 'image/svg+xml'];
            }

            return null;
        }
        $info = @getimagesizefromstring($body);
        if (! $info || $info[0] > 1024 || $info[1] > 1024 || ! in_array($info['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/vnd.microsoft.icon', 'image/x-icon'], true)) {
            return null;
        }

        return ['body' => $body, 'type' => $info['mime']];
    }
}
