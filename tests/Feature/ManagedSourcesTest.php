<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Metribay\Core\Contracts\ManagedSources;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\ManagedSource;
use Metribay\Core\Services\ManagedSourceOffer;
use Metribay\Core\Services\NoManagedSources;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ManagedSourcesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Project $project;

    private string $views;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Managed', 'slug' => 'managed']);
        $this->project = app(WorkspaceContext::class)->run($this->workspace, fn () => Project::create(['name' => 'Managed', 'slug' => 'managed', 'domain' => 'managed.example.test']));
        $this->views = sys_get_temp_dir().'/metribay-managed-views-'.getmypid();
        @mkdir($this->views);
        file_put_contents($this->views.'/host-search.blade.php', '<section id="host-search">Host search content</section>');
        View::addLocation($this->views);
        config(['metribay.project_search.view' => 'host-search']);
    }

    protected function tearDown(): void
    {
        @unlink($this->views.'/host-search.blade.php');
        @rmdir($this->views);
        parent::tearDown();
    }

    /** @param list<ManagedSource> $sources
     * @param list<ManagedSourceOffer> $offers */
    private function fake(array $sources, array $offers = []): void
    {
        $this->app->instance(ManagedSources::class, new class($sources, $offers) implements ManagedSources
        {
            public function __construct(private array $sources, private array $offers) {}

            public function forProject(Workspace $workspace, Project $project): array
            {
                return $this->sources;
            }

            public function offers(Workspace $workspace, Project $project): array
            {
                return $this->offers;
            }
        });
    }

    public function test_standalone_core_has_no_managed_sources(): void
    {
        $this->assertInstanceOf(NoManagedSources::class, app(ManagedSources::class));
        $this->actingAs($this->owner)->get('/w/managed/projects/'.$this->project->id)->assertOk()
            ->assertDontSee('Host search content')->assertDontSee('1 data source');
    }

    public function test_host_search_content_needs_a_managed_source_reporting_there(): void
    {
        $this->fake([new ManagedSource('other', 'Other', 'Vendor', 'healthy', group: null)]);
        $this->actingAs($this->owner)->get('/w/managed/projects/'.$this->project->id)->assertOk()
            ->assertDontSee('Host search content')->assertSee('1 data source');

        $this->fake([new ManagedSource('seo', 'SEO tracking', 'Vendor', 'healthy', group: 'search', anchor: 'host-search')]);
        $this->get('/w/managed/projects/'.$this->project->id)->assertOk()->assertSee('Host search content')->assertDontSee('Add your first data source');
        $this->get('/w/managed/projects/'.$this->project->id.'?view=overview')->assertOk()->assertDontSee('Add your first data source');
        $this->get('/w/managed/projects/'.$this->project->id.'?view=search')->assertOk()->assertSee('Host search content');
    }

    public function test_header_counts_managed_sources_and_links_their_problems(): void
    {
        $this->freezeTime();
        $checked = CarbonImmutable::parse('2026-11-20 08:30:00', 'UTC');
        $this->fake([new ManagedSource('seo', 'SEO tracking', 'Vendor', 'failed', $checked, 'Last check failed', 'search', 'host-search')]);

        $this->actingAs($this->owner)->get('/w/managed/projects/'.$this->project->id)->assertOk()
            ->assertSee('1 data source')
            ->assertSee('href="#host-search">1 needs attention</a>', false)
            ->assertSee('Last sync 20 Nov, 08:30 UTC');

        $this->fake([new ManagedSource('seo', 'SEO tracking', 'Vendor', 'delayed', group: 'search', anchor: 'host-search')]);
        $this->get('/w/managed/projects/'.$this->project->id.'?view=overview')->assertOk()
            ->assertSee('/w/managed/projects/'.$this->project->id.'?days=30#host-search">1 delayed</a>', false);
    }

    public function test_project_settings_list_managed_sources_with_their_status(): void
    {
        $this->fake([new ManagedSource('seo', 'SEO tracking', 'Vendor', 'delayed', null, 'Checks wait for the monthly reset.', 'search', 'host-search', 'https://host.example.test/seo', 'Manage SEO tracking')]);

        $this->actingAs($this->owner)->get('/w/managed/projects/'.$this->project->id.'/settings')->assertOk()
            ->assertSee('id="managed-source-seo"', false)
            ->assertSee('SEO tracking')->assertSee('Managed')->assertSee('Run by Metribay, no account needed')
            ->assertSee('Delayed')->assertSee('No successful sync yet')
            ->assertSee('Checks wait for the monthly reset.')
            ->assertSee('href="https://host.example.test/seo">Manage SEO tracking</a>', false)
            ->assertDontSee('No data sources yet.');
    }

    public function test_provider_picker_offers_managed_sources_in_their_group(): void
    {
        $this->fake([], [
            new ManagedSourceOffer('seo', 'SEO tracking', 'Keyword positions and backlinks', 'search', 'https://host.example.test/seo', added: true),
            new ManagedSourceOffer('odd', 'Odd source', 'Elsewhere', 'unknown-group', 'https://host.example.test/odd'),
        ]);

        $response = $this->actingAs($this->owner)->get('/w/managed/sources/new?project='.$this->project->id)->assertOk();
        $html = $response->getContent();
        $search = substr($html, strpos($html, 'id="provider-group-search"'));
        $search = substr($search, 0, strpos($search, '</section>'));
        $this->assertStringContainsString('href="https://host.example.test/seo"', $search);
        $this->assertStringContainsString('1 added', $search);
        $this->assertStringContainsString('Google Search Console', $search);
        $other = substr($html, strpos($html, 'id="provider-group-other"'));
        $this->assertStringContainsString('href="https://host.example.test/odd"', $other);
    }

    public function test_unknown_status_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ManagedSource('seo', 'SEO tracking', 'Vendor', 'broken');
    }
}
