<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\SitemapUrl;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\Collector;
use Metribay\Core\Services\MetricStore;
use Metribay\Core\Services\ProjectTransfer;
use Metribay\Core\Services\ProviderCatalog;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class SitemapTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private string $catalog = '<urlset/>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-01T10:00:00Z'));
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Docs', 'slug' => 'docs']);
        $this->app->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
        Http::preventStrayRequests();
        Http::fake(['https://example.com/sitemap.xml' => fn () => Http::response($this->catalog)]);
    }

    public function test_baseline_then_additions_are_counted_once_even_when_lastmod_changes_or_events_are_off(): void
    {
        $source = $this->source(false);
        $this->collect($source, ['old' => '2026-10-01']);
        $this->assertSame(0.0, $this->value('2026-11-01'));
        $this->assertNotNull($this->scoped(fn () => $source->fresh()->sitemap_baselined_at));

        $this->travel(1)->days();
        $this->collect($source, ['old' => '2026-11-02', 'new' => null]);
        $this->collect($source, ['old' => '2026-11-02', 'new' => '2026-10-01', 'another' => null]);
        $this->collect($source, ['old' => '2026-11-02', 'new' => '2026-10-01', 'another' => null]);

        $this->assertSame(2.0, $this->value('2026-11-02'));
        $this->assertSame(0, $this->scoped(fn () => ProjectEvent::query()->count()));
        $this->assertSame(0, $this->scoped(fn () => DailyMetric::query()->where('metric', 'published_items')->count()));
        $tracked = $this->scoped(fn () => SitemapUrl::query()->where('url', 'https://example.com/old')->sole());
        $this->assertSame('2026-11-01', $tracked->first_seen_at->toDateString());
        $this->assertSame('2026-11-02', $tracked->last_modified_at->toDateString());
        $this->assertTrue($tracked->is_baseline);
    }

    public function test_removed_and_returning_urls_keep_their_history_through_event_settings(): void
    {
        $source = $this->source();
        $this->collect($source, ['old' => null]);
        $this->travel(1)->days();
        $this->collect($source, ['new' => null]);
        $this->assertSame(1.0, $this->value('2026-11-02'));
        $this->assertSame(2, $this->scoped(fn () => SitemapUrl::query()->count()));

        $this->actingAs($this->owner)->patch('/w/docs/sources/'.$source->id, ['version' => $this->scoped(fn () => $source->fresh()->version), 'label' => $source->label, 'days' => 180, 'enabled' => 1, 'convert_to_events' => '0'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->scoped(fn () => ProjectEvent::query()->count()));
        $this->travel(1)->days();
        $this->collect($source, ['old' => null, 'new' => null]);

        $this->assertSame(0.0, $this->value('2026-11-03'));
        $this->assertFalse($this->scoped(fn () => DailyMetric::query()->where('metric', 'new_urls')->whereDate('date', '2026-11-02')->sole()->is_partial));
        $this->assertSame(1.0, $this->value('2026-11-02'));
        $this->assertSame('2026-11-02', $this->scoped(fn () => SitemapUrl::query()->where('url', 'https://example.com/new')->sole()->first_seen_at->toDateString()));
    }

    public function test_an_empty_first_catalog_is_a_baseline_and_missing_days_stay_unknown(): void
    {
        $source = $this->source(false);
        $this->collect($source, []);
        $this->travel(2)->days();
        $this->collect($source, ['first' => null]);

        $this->assertSame(1.0, $this->value('2026-11-03'));
        $this->assertNull($this->value('2026-11-02'));
        $this->assertFalse($this->scoped(fn () => SitemapUrl::query()->sole()->is_baseline));
    }

    public function test_a_failed_catalog_cannot_advance_the_baseline_or_leave_partial_history(): void
    {
        $source = $this->source();
        $this->catalog = '<urlset><url><loc>https://example.com/old</loc></url><url><loc>https://example.com/new</loc><lastmod>garbage</lastmod></url></urlset>';
        $run = $this->scoped(fn () => app(Collector::class)->run($source));
        $this->assertSame('failed', $run->status);
        $this->assertNull($this->scoped(fn () => $source->fresh()->sitemap_baselined_at));
        $this->assertSame(0, $this->scoped(fn () => SitemapUrl::query()->count()));
        $this->assertNull($this->value('2026-11-01'));
    }

    public function test_a_failed_metric_transaction_rolls_back_tracking_too(): void
    {
        $source = $this->source();
        try {
            $this->scoped(fn () => app(MetricStore::class)->persist($source, new CollectionResult(
                events: [['origin' => 'not_allowed', 'external_id' => 'bad', 'title' => 'Bad', 'url' => '', 'occurred_at' => null]],
                sitemapUrls: [['url' => 'https://example.com/old', 'last_modified_at' => null]],
            ), CarbonImmutable::now('UTC')));
            $this->fail('Invalid event must reject the transaction.');
        } catch (\InvalidArgumentException) {
            $this->assertNull($this->scoped(fn () => $source->fresh()->sitemap_baselined_at));
            $this->assertSame(0, $this->scoped(fn () => SitemapUrl::query()->count()));
            $this->assertNull($this->value('2026-11-01'));
        }
    }

    public function test_tracking_is_scoped_to_each_source_and_workspace(): void
    {
        $source = $this->source(false);
        $this->collect($source, ['old' => null]);
        $other = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Other', 'slug' => 'other']);
        $this->workspace = $other;
        $second = $this->source(false);
        $this->collect($second, []);
        $this->travel(1)->days();
        $this->collect($second, ['old' => null]);

        $this->assertSame(1.0, $this->value('2026-11-02'));
        $this->assertSame(1, $this->scoped(fn () => SitemapUrl::query()->count()));
        $this->assertNull($this->scoped(fn () => Source::query()->find($source->id)));
    }

    public function test_new_urls_are_visible_in_the_overview_and_project_content_section(): void
    {
        $source = $this->source(false);
        $this->collect($source, []);
        $this->travel(1)->days();
        $this->collect($source, ['new' => null]);

        $this->actingAs($this->owner)->get('/w/docs')->assertOk()->assertSee('New URLs detected')->assertDontSee('Items published');
        $response = $this->get('/w/docs/projects/'.$source->project_id);
        $response->assertOk()->assertSee('Content')->assertSee('New URLs detected')->assertSee('Published URLs');
        $this->assertMatchesRegularExpression('/id="group-content"[\s\S]*?chart-'.$source->id.'-new_urls-label/', $response->getContent());
    }

    public function test_moving_a_project_preserves_the_baseline_and_first_seen_history(): void
    {
        $source = $this->source(false);
        $this->collect($source, ['old' => null]);
        $this->travel(1)->days();
        $this->collect($source, ['old' => null, 'new' => null]);
        $other = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Other', 'slug' => 'other']);
        $moved = app(ProjectTransfer::class)->move($this->workspace, $source->project_id, $other, 1, false);
        $this->workspace = $other;
        $source = $this->scoped(fn () => Source::query()->where('project_id', $moved['project'])->sole());
        $this->travel(1)->days();
        $this->collect($source, ['old' => null, 'new' => null]);

        $this->assertSame(0.0, $this->value('2026-11-03'));
        $this->assertSame(1.0, $this->value('2026-11-02'));
        $this->assertSame(2, $this->scoped(fn () => SitemapUrl::query()->count()));
        $this->assertSame('2026-11-01', $source->sitemap_baselined_at->toDateString());
    }

    private function source(bool $events = true): Source
    {
        return $this->scoped(fn () => Source::create(['project_id' => Project::create(['name' => 'Docs', 'slug' => 'docs'])->id,
            'kind' => 'sitemap', 'label' => 'Docs sitemap', 'config' => ['url' => 'https://example.com/sitemap.xml', 'days' => 180, 'convert_to_events' => $events],
            'reporting_timezone' => 'UTC', 'identity_hash' => ProviderCatalog::identityHash('sitemap', ['url' => 'https://example.com/sitemap.xml'], 'UTC')]));
    }

    /** @param array<string, string|null> $urls */
    private function collect(Source $source, array $urls): void
    {
        $xml = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $path => $lastmod) {
            $xml .= '<url><loc>https://example.com/'.$path.'</loc>'.($lastmod === null ? '' : '<lastmod>'.$lastmod.'</lastmod>').'</url>';
        }
        $this->catalog = $xml.'</urlset>';
        $run = $this->scoped(fn () => app(Collector::class)->run($source->fresh()));
        $this->assertSame('success', $run->status, $run->error_code ?? '');
    }

    private function value(string $date): ?float
    {
        return $this->scoped(fn () => DailyMetric::query()->where('metric', 'new_urls')->whereDate('date', $date)->value('value'));
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback(...));
    }
}
