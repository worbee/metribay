<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use SimplePie\Item;
use SimplePie\SimplePie;

/**
 * Published entries from one RSS or Atom feed. SimplePie reads both formats.
 * Days the feed does not mention stay unknown. Each dated entry is also an event.
 */
final class FeedConnector extends ContentConnector
{
    public const MAX_ITEMS = 500;

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'Feed');
        $url = ContentUrl::normalize($source->config['url'] ?? null);
        $configuration = new SourceConfiguration($source->kind, $source->config, $source->reportingTimezone);
        [$start, $today] = $this->window($configuration, $now, 1, 180);
        $now = $now->utc();
        [$body, $final] = $this->document($url, 'The feed');
        $feed = new SimplePie;
        $feed->set_raw_data($body);
        $feed->enable_cache(false);
        $feed->force_feed(true);
        $feed->set_output_encoding('UTF-8');
        if (! $feed->init()) {
            throw new ConnectorException('This URL did not return a readable RSS or Atom feed.');
        }
        $type = $feed->get_type();
        if (($type & (SimplePie::TYPE_RSS_ALL | SimplePie::TYPE_ATOM_ALL)) === 0) {
            throw new ConnectorException('This URL did not return an RSS or Atom feed.');
        }
        $items = $feed->get_items();
        if (count($items) > self::MAX_ITEMS) {
            throw new ConnectorException('This feed has more entries than Metribay can store. No partial totals were accepted.');
        }
        $counts = [];
        $events = [];
        $seen = [];
        $dated = false;
        foreach ($items as $item) {
            $stamp = $item->get_date('U');
            if (! is_int($stamp)) {
                continue;
            }
            $dated = true;
            $published = CarbonImmutable::createFromTimestampUTC($stamp);
            if ($published > $now) {
                throw new ConnectorException('The feed returned a future publication date.');
            }
            if ($published < $start) {
                continue;
            }
            $id = $this->identity($item);
            if (isset($seen[$id])) {
                throw new ConnectorException('The feed repeated an entry. No partial totals were accepted.');
            }
            $seen[$id] = true;
            $date = $published->toDateString();
            $counts[$date] = ($counts[$date] ?? 0) + 1;
            $title = $this->plain((string) $item->get_title(), 200);
            $events[] = [
                'origin' => 'feed_item',
                'external_id' => $id,
                'title' => $title !== '' ? $title : 'Untitled',
                'body' => $this->plain((string) $item->get_description(), 4000),
                'url' => $this->link($item->get_permalink(), $final),
                'occurred_at' => $published->toIso8601String(),
            ];
        }
        if ($items !== [] && ! $dated) {
            throw new ConnectorException('This feed has entries, but none of them has a publication date.');
        }
        ksort($counts);
        $daily = [];
        foreach ($counts as $date => $count) {
            $daily[] = $this->daily('published_items', $date, (float) $count, $date === $today->toDateString());
        }

        return new CollectionResult(daily: $daily, events: $events, authoritativeEvents: ['feed_item' => $start->utc()->toIso8601String()]);
    }

    private function identity(Item $item): string
    {
        $id = $item->get_id(false);
        if (! is_string($id) || $id === '' || preg_match('/[\x00-\x1F\x7F]/', $id) === 1 || strlen($id) > 120) {
            $id = hash('sha256', (string) $item->get_permalink().(string) $item->get_title().(string) $item->get_date('U'));
        }

        return $id;
    }

    private function link(mixed $url, string $base): string
    {
        if (! is_string($url) || $url === '') {
            return '';
        }
        try {
            return ContentUrl::page((string) UriResolver::resolve(new Uri($base), new Uri($url)));
        } catch (ConnectorException|\InvalidArgumentException) {
            return '';
        }
    }
}
