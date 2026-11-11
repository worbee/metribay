<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Metribay\Core\Connectors\SitemapConnector;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\SitemapUrl;
use Metribay\Core\Models\Source;

/** Durable first-seen history. Called inside the metric transaction, under the source lock. */
final class SitemapTracking
{
    /**
     * @param  list<array{url: string, last_modified_at: string|null}>  $urls
     * @return array{metric: string, date: string, value: float, is_partial: bool}
     */
    public function record(Source $source, array $urls, CarbonImmutable $now): array
    {
        if ($source->kind !== 'sitemap' || count($urls) > SitemapConnector::MAX_URLS) {
            throw new InvalidArgumentException('Invalid sitemap catalog.');
        }
        $source = Source::query()->lockForUpdate()->findOrFail($source->id);
        $now = $now->utc();
        $baseline = $source->sitemap_baselined_at === null;
        $rows = [];
        foreach ($urls as $entry) {
            $url = $entry['url'];
            $parts = parse_url($url);
            $hash = hash('sha256', $url);
            if (! $parts || ! isset($parts['host']) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || isset($rows[$hash])) {
                throw new InvalidArgumentException('Invalid sitemap URL.');
            }
            $modified = $entry['last_modified_at'] === null ? null : CarbonImmutable::parse($entry['last_modified_at'])->utc();
            if ($modified?->gt($now)) {
                throw new InvalidArgumentException('Invalid sitemap modification date.');
            }
            $rows[$hash] = ['workspace_id' => $source->workspace_id, 'source_id' => $source->id, 'url_hash' => $hash, 'url' => $url,
                'first_seen_at' => $now, 'last_seen_at' => $now, 'last_modified_at' => $modified, 'is_baseline' => $baseline];
        }
        foreach (array_chunk(array_values($rows), 100) as $chunk) {
            SitemapUrl::query()->upsert($chunk, ['workspace_id', 'source_id', 'url_hash'], ['url', 'last_seen_at', 'last_modified_at']);
        }
        if ($baseline) {
            $source->forceFill(['sitemap_baselined_at' => $now])->save();
        }
        // Recorded detection days become final after midnight; unobserved days remain gaps.
        DailyMetric::query()->where('source_id', $source->id)->where('metric', 'new_urls')
            ->whereDate('date', '<', $now->toDateString())->whereDate('date', '>', $source->sitemap_baselined_at->toDateString())->update(['is_partial' => false]);
        $count = SitemapUrl::query()->where('source_id', $source->id)->where('is_baseline', false)
            ->where('first_seen_at', '>=', $now->startOfDay())->where('first_seen_at', '<', $now->startOfDay()->addDay())->count();

        return ['metric' => 'new_urls', 'date' => $now->toDateString(), 'value' => (float) $count, 'is_partial' => true];
    }
}
