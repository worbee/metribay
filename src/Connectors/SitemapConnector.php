<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;

/**
 * Published URLs from one sitemap. A sitemap index is read through to its urlsets.
 * The current URL count is a snapshot. The store tracks first-seen URLs independently
 * of optional events; lastmod is modification metadata, never a publication date.
 */
final class SitemapConnector extends ContentConnector
{
    public const MAX_URLS = 2000;

    public const MAX_DOCUMENTS = 50;

    private const NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    private const DOCUMENT_LIMIT_ERROR = 'This sitemap index lists more sitemaps than Metribay can read. No partial totals were accepted.';

    private const URL_LIMIT_ERROR = 'This sitemap has more URLs than Metribay can store. No partial totals were accepted.';

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'Sitemap');
        $url = ContentUrl::normalize($source->config['url'] ?? null);
        $configuration = new SourceConfiguration($source->kind, $source->config, $source->reportingTimezone);
        [, $today] = $this->window($configuration, $now, 1, 180);
        $now = $now->utc();
        $seen = [];
        $root = $this->fetch($url, $seen);
        $name = $root->documentElement?->localName;
        if ($name === 'sitemapindex') {
            $children = $this->entries($root, 'sitemap', self::MAX_DOCUMENTS - 1);
            $urls = [];
            foreach ($children as $child) {
                $document = $this->fetch($child['loc'], $seen);
                if ($document->documentElement?->localName !== 'urlset') {
                    throw new ConnectorException('A sitemap index must list sitemap files, not another index.');
                }
                $urls = [...$urls, ...$this->entries($document, 'url', self::MAX_URLS - count($urls))];
            }
        } elseif ($name === 'urlset') {
            $urls = $this->entries($root, 'url', self::MAX_URLS);
        } else {
            throw new ConnectorException('This URL did not return a sitemap or a sitemap index.');
        }
        $catalog = [];
        $events = [];
        $seenUrls = [];
        foreach ($urls as $entry) {
            $loc = $entry['loc'];
            if (isset($seenUrls[$loc])) {
                throw new ConnectorException('The sitemap repeated a URL. No partial totals were accepted.');
            }
            $seenUrls[$loc] = true;
            $occurred = null;
            if ($entry['lastmod'] !== null) {
                $published = $this->moment($entry['lastmod']);
                if ($published > $now) {
                    throw new ConnectorException('The sitemap returned a future lastmod date.');
                }
                $occurred = $published->toIso8601String();
            }
            $catalog[] = ['url' => $loc, 'last_modified_at' => $occurred];
            $events[] = [
                'origin' => 'sitemap_url',
                'external_id' => hash('sha256', $loc),
                'title' => $this->title($loc),
                'body' => '',
                'url' => $loc,
                'occurred_at' => $occurred,
            ];
        }
        $daily = [];
        $daily[] = $this->daily('published_urls', $today->toDateString(), (float) count($urls));

        // The sitemap is the current catalog, so URLs that disappeared are removed at any age.
        return new CollectionResult(daily: $daily, events: $events, authoritativeEvents: ['sitemap_url' => null],
            replaceDailyDates: [$today->toDateString() => ['published_urls']], sitemapUrls: $catalog);
    }

    /**
     * @param  array<string, true>  $seen
     */
    private function fetch(string $url, array &$seen): DOMDocument
    {
        $url = ContentUrl::normalize($url);
        if (isset($seen[$url])) {
            throw new ConnectorException('The sitemap pointed at a document that was already read.');
        }
        [$body, $final] = $this->document($url, 'The sitemap');
        if (isset($seen[$final])) {
            throw new ConnectorException('The sitemap pointed at a document that was already read.');
        }
        $seen[$url] = true;
        $seen[$final] = true;
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadXML($body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($loaded !== true || $document->doctype !== null || ! $document->documentElement instanceof DOMElement) {
            throw new ConnectorException('The sitemap is not valid XML.');
        }
        $namespace = $document->documentElement->namespaceURI;
        if (! in_array($namespace, [null, '', self::NAMESPACE], true)) {
            throw new ConnectorException('The sitemap uses an unsupported namespace.');
        }

        return $document;
    }

    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    private function entries(DOMDocument $document, string $wrapper, int $remaining): array
    {
        $root = $document->documentElement;
        $namespace = $root?->namespaceURI;
        $nodes = ($namespace === self::NAMESPACE)
            ? $document->getElementsByTagNameNS(self::NAMESPACE, $wrapper)
            : $document->getElementsByTagName($wrapper);
        $entries = [];
        foreach ($nodes as $node) {
            if ($node->parentNode !== $root) {
                continue;
            }
            if (count($entries) >= $remaining) {
                throw new ConnectorException($wrapper === 'sitemap' ? self::DOCUMENT_LIMIT_ERROR : self::URL_LIMIT_ERROR);
            }
            $loc = null;
            $lastmod = null;
            foreach ($node->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }
                if ($namespace === self::NAMESPACE && $child->namespaceURI !== self::NAMESPACE) {
                    continue;
                }
                if ($child->localName === 'loc' && $loc === null) {
                    $loc = trim($child->textContent);
                } elseif ($child->localName === 'lastmod' && $lastmod === null && trim($child->textContent) !== '') {
                    $lastmod = trim($child->textContent);
                }
            }
            if ($loc === null || $loc === '') {
                throw new ConnectorException('A sitemap entry has no address.');
            }
            $entries[] = ['loc' => $wrapper === 'sitemap' ? ContentUrl::normalize($loc) : ContentUrl::page($loc), 'lastmod' => $lastmod];
        }

        return $entries;
    }

    private function moment(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1) {
            return CarbonImmutable::parse($this->date($value, 'Sitemap').'T00:00:00Z');
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})(Z|[+-]\d{2}:\d{2})$/D', $value, $parts) === 1) {
            $value = $parts[1].':00'.$parts[2];
        }

        return $this->timestamp($value, 'Sitemap');
    }

    private function title(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $title = ($path === '' || $path === '/') ? (string) ($parts['host'] ?? $url) : $path;
        if (isset($parts['query']) && $parts['query'] !== '') {
            $title .= '?'.$parts['query'];
        }
        $title = $this->plain($title, 200);

        return $title !== '' ? $title : $url;
    }
}
