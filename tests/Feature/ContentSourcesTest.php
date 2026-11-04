<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\Collector;
use Metribay\Core\Services\ProviderCatalog;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ContentSourcesTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-25T10:00:00Z'));
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Content', 'slug' => 'content']);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        Http::preventStrayRequests();
    }

    public function test_setup_stores_the_event_preference_and_settings_can_turn_it_off(): void
    {
        Queue::fake();
        $project = $this->scoped(fn () => Project::create(['name' => 'Example', 'slug' => 'example']));
        $this->actingAs($this->owner)->get('/w/content/sources/new?project='.$project->id);
        $this->post('/w/content/sources/setup', ['action' => 'provider', 'kind' => 'feed'])->assertRedirect();
        $this->get('/w/content/sources/new')->assertOk()->assertSee('Feed URL')->assertDontSee('Connected account');
        $this->post('/w/content/sources/setup', ['action' => 'manual', 'resource_id' => 'https://Example.com/feed', 'timezone' => 'UTC'])->assertSessionHasNoErrors();
        $this->get('/w/content/sources/new')->assertOk()->assertSee('Automatically convert these values into events');
        $this->post('/w/content/sources/setup', ['action' => 'finish', 'days' => 180, 'convert_to_events' => '0'])->assertSessionHasNoErrors();
        $source = $this->scoped(fn () => Source::query()->sole());
        $this->assertSame('https://example.com/feed', $source->config['url']);
        $this->assertFalse($source->config['convert_to_events']);
        $this->assertNull($source->connection_id);
        Queue::assertPushed(CollectSource::class);

        $this->event($source, 'feed_item', 'kept');
        $this->actingAs($this->owner)->patch('/w/content/sources/'.$source->id, ['version' => $source->version, 'label' => $source->label, 'days' => 90, 'enabled' => 1, 'convert_to_events' => '1'])
            ->assertSessionHas('status', 'Data source updated. Events are being imported again.');
        $this->assertTrue($this->scoped(fn () => $source->fresh())->config['convert_to_events']);

        $this->actingAs($this->owner)->patch('/w/content/sources/'.$source->id, ['version' => $source->version + 1, 'label' => $source->label, 'days' => 90, 'enabled' => 1, 'convert_to_events' => '0'])
            ->assertSessionHas('status', 'Data source updated. These values will no longer be added as events.');
        $this->assertSame(0, $this->scoped(fn () => ProjectEvent::query()->count()));
        $this->assertSame(ProviderCatalog::identityHash('feed', ['url' => 'https://example.com/feed'], 'UTC'), $this->scoped(fn () => $source->fresh())->identity_hash);
    }

    public function test_collection_skips_events_when_the_preference_is_off_and_keeps_older_ones(): void
    {
        Http::fake(['https://example.com/feed' => Http::response(<<<'XML'
            <?xml version="1.0"?><rss version="2.0"><channel><title>Example</title>
            <item><title>Hello</title><guid>hello</guid><pubDate>Sat, 24 Oct 2026 08:00:00 GMT</pubDate><description>A note</description></item>
            </channel></rss>
            XML)]);
        $source = $this->scoped(fn () => Source::create(['project_id' => Project::create(['name' => 'Example', 'slug' => 'example'])->id, 'kind' => 'feed', 'label' => 'Blog',
            'config' => ['url' => 'https://example.com/feed', 'days' => 25, 'convert_to_events' => false], 'reporting_timezone' => 'UTC',
            'identity_hash' => ProviderCatalog::identityHash('feed', ['url' => 'https://example.com/feed'], 'UTC')]));
        $this->event($source, 'feed_item', 'already-there');
        $run = $this->scoped(fn () => app(Collector::class)->run($source->fresh()));
        $this->assertSame('success', $run->status);
        $this->assertSame(1, $this->scoped(fn () => DailyMetric::query()->where('metric', 'published_items')->count()));
        $this->assertSame(['already-there'], $this->scoped(fn () => ProjectEvent::query()->pluck('external_id')->all()));
    }

    public function test_a_sitemap_url_without_lastmod_keeps_the_first_day_it_was_seen(): void
    {
        Http::fake(['https://example.com/sitemap.xml' => Http::response(<<<'XML'
            <?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/docs</loc></url></urlset>
            XML)]);
        $source = $this->scoped(fn () => Source::create(['project_id' => Project::create(['name' => 'Docs', 'slug' => 'docs'])->id, 'kind' => 'sitemap', 'label' => 'Docs',
            'config' => ['url' => 'https://example.com/sitemap.xml', 'days' => 25], 'reporting_timezone' => 'UTC',
            'identity_hash' => ProviderCatalog::identityHash('sitemap', ['url' => 'https://example.com/sitemap.xml'], 'UTC')]));
        $this->scoped(fn () => app(Collector::class)->run($source));
        $this->travel(2)->days();
        $this->scoped(fn () => app(Collector::class)->run($source->fresh()));
        $event = $this->scoped(fn () => ProjectEvent::query()->sole());
        $this->assertSame('sitemap_url', $event->origin);
        $this->assertSame('2026-10-25', $event->date->toDateString());
        $this->assertSame(1.0, (float) $this->scoped(fn () => DailyMetric::query()->where('metric', 'published_urls')->latest('date')->value('value')));
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback(...));
    }

    private function event(Source $source, string $origin, string $id): void
    {
        $this->scoped(fn () => ProjectEvent::query()->create(['project_id' => $source->project_id, 'source_id' => $source->id, 'origin' => $origin, 'external_id' => $id,
            'date' => '2026-10-20', 'occurred_at' => CarbonImmutable::parse('2026-10-20T10:00:00Z'), 'title' => $id, 'body' => '', 'url' => 'https://example.com/'.$id]));
    }
}
