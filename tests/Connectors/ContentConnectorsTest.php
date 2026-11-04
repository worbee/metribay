<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\ContentUrl;
use Metribay\Core\Connectors\FeedConnector;
use Metribay\Core\Connectors\ProviderRequestLimit;
use Metribay\Core\Connectors\SitemapConnector;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Services\ProviderCatalog;

require_once __DIR__.'/FixtureTestCase.php';

final class ContentConnectorsTest extends FixtureTestCase
{
    public function test_one_feed_provider_reads_rss_and_atom_into_the_same_events(): void
    {
        Http::fake(['https://example.com/feed' => Http::response($this->rss()), 'https://example.com/atom' => Http::response($this->atom())]);
        $rss = $this->feed('https://example.com/feed');
        $this->assertSame([
            ['metric' => 'published_items', 'date' => '2026-10-24', 'value' => 1.0, 'is_partial' => false],
            ['metric' => 'published_items', 'date' => '2026-10-25', 'value' => 1.0, 'is_partial' => true],
        ], $rss->daily);
        $this->assertEqualsCanonicalizing(['Hello', 'Today'], array_column($rss->events, 'title'));
        $hello = array_values(array_filter($rss->events, fn (array $event): bool => $event['title'] === 'Hello'))[0];
        $this->assertSame('feed_item', $hello['origin']);
        $this->assertSame('https://example.com/hello', $hello['url']);
        $this->assertSame('2026-10-01T00:00:00+00:00', $rss->authoritativeEvents['feed_item']);
        $atom = $this->feed('https://example.com/atom');
        $this->assertSame('Atom note', $atom->events[0]['title']);
        $this->assertSame('urn:example:atom', $atom->events[0]['external_id']);
    }

    public function test_a_feed_without_any_dates_fails_and_a_future_date_fails(): void
    {
        Http::fake(['https://example.com/feed' => Http::response(<<<'XML'
            <?xml version="1.0"?><rss version="2.0"><channel><title>Example</title><item><title>Undated</title></item></channel></rss>
            XML)]);
        $this->expectException(ConnectorException::class);
        $this->expectExceptionMessage('publication date');
        $this->feed('https://example.com/feed');
    }

    public function test_a_future_feed_date_is_rejected(): void
    {
        Http::fake(['https://example.com/feed' => Http::response(<<<'XML'
            <?xml version="1.0"?><rss version="2.0"><channel><title>Example</title><item><title>Later</title><pubDate>Mon, 26 Oct 2026 00:00:00 GMT</pubDate></item></channel></rss>
            XML)]);
        $this->expectException(ConnectorException::class);
        $this->expectExceptionMessage('future');
        $this->feed('https://example.com/feed');
    }

    public function test_a_sitemap_index_is_read_through_to_its_urlset(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/sitemap.xml')) {
                return Http::response(<<<'XML'
                    <?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://example.com/pages.xml</loc></sitemap></sitemapindex>
                    XML);
            }

            return Http::response(<<<'XML'
                <?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <url><loc>https://example.com/docs</loc><lastmod>2026-10-24</lastmod></url>
                <url><loc>https://example.com/old</loc><lastmod>2020-01-01T00:00:00Z</lastmod></url>
                <url><loc>http://example.com/plain</loc></url>
                </urlset>
                XML);
        });
        $result = $this->sitemap('https://example.com/sitemap.xml');
        $this->assertSame(3.0, $result->daily[array_key_last($result->daily)]['value']);
        $this->assertSame('published_urls', $result->daily[array_key_last($result->daily)]['metric']);
        $this->assertSame(['2026-10-25' => ['published_urls']], $result->replaceDailyDates);
        $this->assertNull($result->authoritativeEvents['sitemap_url']);
        $this->assertCount(3, $result->events);
        $this->assertSame('2026-10-24T00:00:00+00:00', $result->events[0]['occurred_at']);
        $this->assertNull($result->events[2]['occurred_at']);
        $this->assertSame([['metric' => 'published_items', 'date' => '2026-10-24', 'value' => 1.0, 'is_partial' => false]], array_values(array_filter($result->daily, fn (array $row): bool => $row['metric'] === 'published_items')));
        Http::assertSentCount(2);
    }

    public function test_a_nested_sitemap_index_is_rejected(): void
    {
        Http::fake(['https://example.com/sitemap.xml' => Http::response(<<<'XML'
            <?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://example.com/more.xml</loc></sitemap></sitemapindex>
            XML), 'https://example.com/more.xml' => Http::response(<<<'XML'
            <?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://example.com/pages.xml</loc></sitemap></sitemapindex>
            XML)]);
        $this->expectException(ConnectorException::class);
        $this->expectExceptionMessage('another index');
        $this->sitemap('https://example.com/sitemap.xml');
    }

    public function test_a_document_type_is_rejected(): void
    {
        Http::fake(['https://example.com/sitemap.xml' => Http::response('<!DOCTYPE urlset [<!ENTITY x "x">]><urlset></urlset>')]);
        $this->expectException(ConnectorException::class);
        $this->expectExceptionMessage('document type');
        $this->sitemap('https://example.com/sitemap.xml');
    }

    public function test_redirects_are_revalidated_one_https_hop_at_a_time(): void
    {
        Http::fake([
            'https://example.com/feed' => Http::response('', 302, ['Location' => 'https://cdn.example/feed']),
            'https://cdn.example/feed' => Http::response($this->rss()),
        ]);
        $this->assertCount(2, $this->feed('https://example.com/feed')->events);
        Http::assertSentCount(2);
    }

    public function test_relative_entry_links_use_the_final_feed_address(): void
    {
        Http::fake([
            'https://example.com/feed' => Http::response('', 302, ['Location' => 'https://cdn.example/news/updates/feed.xml']),
            'https://cdn.example/news/updates/feed.xml' => Http::response(str_replace(['<link>https://example.com/</link>', 'https://example.com/hello'], ['', '../hello'], $this->rss())),
        ]);

        $this->assertContains('https://cdn.example/news/hello', array_column($this->feed('https://example.com/feed')->events, 'url'));
        Http::assertSentCount(2);
    }

    public function test_sitemap_index_enforces_the_combined_url_limit(): void
    {
        $urlset = static fn (int $start, int $length): string => '<urlset>'.implode('', array_map(
            static fn (int $number): string => "<url><loc>https://example.com/page/{$number}</loc></url>",
            range($start, $start + $length - 1),
        )).'</urlset>';
        Http::fake([
            'https://example.com/sitemap.xml' => Http::response('<sitemapindex><sitemap><loc>https://example.com/first.xml</loc></sitemap><sitemap><loc>https://example.com/second.xml</loc></sitemap></sitemapindex>'),
            'https://example.com/first.xml' => Http::response($urlset(1, 1200)),
            'https://example.com/second.xml' => Http::response($urlset(1201, 801)),
        ]);

        $this->expectException(ConnectorException::class);
        $this->expectExceptionMessage('more URLs');
        $this->sitemap('https://example.com/sitemap.xml');
    }

    public function test_a_rate_limit_stops_the_run(): void
    {
        Http::fake(['https://example.com/feed' => Http::response('', 429)]);
        $this->expectException(ProviderRequestLimit::class);
        $this->feed('https://example.com/feed');
    }

    public function test_catalog_normalizes_the_address_and_keeps_the_event_preference_out_of_identity(): void
    {
        $this->assertSame('https://example.com/feed', ContentUrl::normalize('https://Example.com/feed'));
        $config = ProviderCatalog::source('feed', ['url' => 'https://Example.com/feed', 'convert_to_events' => '0'], 'UTC');
        $this->assertFalse($config['convert_to_events']);
        $this->assertSame(180, $config['days']);
        $this->assertTrue(ProviderCatalog::importsEvents([]));
        $this->assertFalse(ProviderCatalog::importsEvents(['convert_to_events' => false]));
        $this->assertSame(ProviderCatalog::identityHash('feed', $config, 'UTC'), ProviderCatalog::identityHash('feed', ['url' => 'https://example.com/feed', 'days' => 30, 'convert_to_events' => true], 'UTC'));
        $this->assertSame(ProviderCatalog::identityHash('github', ['repository' => 'a/b', 'convert_to_events' => false], 'UTC'), ProviderCatalog::identityHash('github', ['repository' => 'a/b'], 'UTC'));
        $this->assertFalse(ProviderCatalog::requiresConnection('sitemap'));
        $this->assertSame(['github_release'], ProviderCatalog::eventPreferenceOrigins('github'));
        $stripped = ProviderCatalog::withoutConvertedEvents('github', ['convert_to_events' => false], new CollectionResult(
            events: [
                ['origin' => 'github_release', 'external_id' => 'r', 'title' => 'v1', 'url' => 'https://example.com/r', 'occurred_at' => '2026-10-24T00:00:00Z'],
                ['origin' => 'github_deployment', 'external_id' => 'd', 'title' => 'production', 'url' => 'https://example.com/d', 'occurred_at' => '2026-10-24T00:00:00Z'],
            ],
            authoritativeEvents: ['github_release' => '2026-10-01T00:00:00Z', 'github_deployment' => '2026-10-01T00:00:00Z'],
        ));
        $this->assertSame(['github_deployment'], array_column($stripped->events, 'origin'));
        $this->assertArrayNotHasKey('github_release', $stripped->authoritativeEvents);
        $this->assertArrayHasKey('github_deployment', $stripped->authoritativeEvents);
    }

    private function rss(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0"><channel><title>Example</title><link>https://example.com/</link><description>Notes</description>
            <item><title>Hello</title><link>https://example.com/hello</link><guid>https://example.com/hello</guid><pubDate>Sat, 24 Oct 2026 08:00:00 GMT</pubDate><description>A note</description></item>
            <item><title>Today</title><link>https://example.com/today</link><guid>https://example.com/today</guid><pubDate>Sun, 25 Oct 2026 09:00:00 GMT</pubDate><description>Partial</description></item>
            <item><title>Old</title><guid>old</guid><pubDate>Wed, 01 Jan 2020 00:00:00 GMT</pubDate></item>
            </channel></rss>
            XML;
    }

    private function atom(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <feed xmlns="http://www.w3.org/2005/Atom"><title>Example</title><id>https://example.com/atom</id><updated>2026-10-24T08:00:00Z</updated>
            <entry><title>Atom note</title><id>urn:example:atom</id><updated>2026-10-24T08:00:00Z</updated><link href="https://example.com/atom-note"/></entry></feed>
            XML;
    }

    private function feed(string $url): CollectionResult
    {
        return (new FeedConnector($this->http()))->collect(new SourceConfiguration('feed', ['url' => $url, 'days' => 25], 'UTC'), [], CarbonImmutable::parse('2026-10-25T10:00:00Z'));
    }

    private function sitemap(string $url): CollectionResult
    {
        return (new SitemapConnector($this->http()))->collect(new SourceConfiguration('sitemap', ['url' => $url, 'days' => 25], 'UTC'), [], CarbonImmutable::parse('2026-10-25T10:00:00Z'));
    }
}
