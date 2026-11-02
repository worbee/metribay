<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\ProjectFavicon;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ProjectFaviconTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/a9sAAAAASUVORK5CYII=';

    public function test_favicon_is_cached_and_served_only_with_project_access(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://example.test/favicon.ico' => Http::response(base64_decode(self::PNG), 200), 'https://svg.example.test/favicon.ico' => Http::response('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>')]);
        $this->app->instance(ProjectFavicon::class, new ProjectFavicon(new SafeHttpClient(fn () => ['93.184.216.34'])));
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Icons', 'slug' => 'icons']);
        $project = app(WorkspaceContext::class)->run($workspace, fn () => Project::create(['name' => 'Example', 'slug' => 'example', 'domain' => 'example.test']));
        $url = '/w/icons/projects/'.$project->id.'/favicon';
        $this->actingAs($owner)->get('/w/icons')->assertOk()->assertSee('class="project-favicon"', false)->assertSee($url)->assertDontSee('src="https://example.test', false);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent(base64_decode(self::PNG));
        $this->get($url)->assertOk();
        Http::assertSentCount(1);
        $project->domain = 'svg.example.test';
        app(WorkspaceContext::class)->run($workspace, fn () => $project->save());
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
        Http::assertSentCount(2);
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        Http::assertSentCount(2);
    }

    public function test_discovery_finds_a_local_icon_and_caches_invalid_or_missing_icons(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://example.test/favicon.ico' => Http::response('not an image', 200),
            'https://example.test/' => Http::response('<link rel="shortcut icon" href="/assets/icon.png"><link rel="icon" href="https://foreign.test/icon.png">'),
            'https://example.test/assets/icon.png' => Http::response(base64_decode(self::PNG)),
            'https://missing.test/*' => Http::response('<html>No icon</html>'),
        ]);
        $service = new ProjectFavicon(new SafeHttpClient(fn () => ['93.184.216.34']));
        $this->assertSame('image/png', $service->forDomain('example.test')['type']);
        $this->assertNull($service->forDomain('missing.test'));
        $this->assertNull($service->forDomain('missing.test'));
        Http::assertSentCount(5);
        $this->assertNull((new ProjectFavicon(new SafeHttpClient(fn () => ['127.0.0.1'])))->forDomain('private.test'));
        Http::assertSentCount(5);
    }

    public function test_redirects_revalidate_every_destination_and_never_fetch_private_addresses(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://redirect.test/favicon.ico' => Http::response('', 302, ['Location' => 'https://cdn.test/icon.png']),
            'https://cdn.test/icon.png' => Http::response(base64_decode(self::PNG)),
            'https://unsafe.test/favicon.ico' => Http::response('', 302, ['Location' => 'https://private.test/icon.png']),
        ]);
        $service = new ProjectFavicon(new SafeHttpClient(fn ($host) => $host === 'private.test' ? ['127.0.0.1'] : ['93.184.216.34']));
        $this->assertSame('image/png', $service->forDomain('redirect.test')['type']);
        $this->assertNull($service->forDomain('unsafe.test'));
        Http::assertSentCount(3);
    }
}
