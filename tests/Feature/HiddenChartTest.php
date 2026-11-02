<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\HiddenChart;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\PersonalWorkspaceAccess;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class HiddenChartTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Project $project;

    private Source $source;

    /** Charts a GitHub data source always shows. */
    private const KEYS = ['commits', 'prs_merged', 'loc_changed', 'open_issues'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Charts', 'slug' => 'charts']);
        [$this->project, $this->source] = $this->scoped(function () {
            $project = Project::create(['name' => 'Example', 'slug' => 'example']);
            $source = Source::create(['project_id' => $project->id, 'kind' => 'github', 'label' => 'Repository', 'identity_hash' => str_repeat('c', 64), 'config' => ['repository' => 'example/app', 'days' => 30], 'reporting_timezone' => 'UTC', 'last_success_at' => now()]);
            foreach (['commits', 'prs_merged'] as $metric) {
                DailyMetric::create(['source_id' => $source->id, 'metric' => $metric, 'date' => now()->subDays(2)->toDateString(), 'value' => 3, 'is_partial' => false, 'collected_at' => now()]);
            }

            return [$project, $source];
        });
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback);
    }

    private function url(string $path = ''): string
    {
        return '/w/charts/projects/'.$this->project->id.$path;
    }

    /** @return list<string> metric keys rendered as charts on the project page */
    private function chartKeys(string $html): array
    {
        preg_match_all('/id="chart-'.$this->source->id.'-([a-z_0-9]+)-label"/', $html, $matches);

        return $matches[1];
    }

    public function test_hiding_a_chart_removes_it_from_the_project_page_and_offers_undo(): void
    {
        $page = $this->actingAs($this->owner)->get($this->url())->assertOk()->assertSee('Hide Commits chart')->getContent();
        $this->assertSame(self::KEYS, $this->chartKeys($page));

        $this->from($this->url().'?days=7')->post($this->url('/hidden-charts'), ['source_id' => $this->source->id, 'metric_key' => 'commits'])
            ->assertRedirect($this->url().'?days=7#source-'.$this->source->id);
        // Repeated submissions keep a single record.
        $this->from($this->url())->post($this->url('/hidden-charts'), ['source_id' => $this->source->id, 'metric_key' => 'commits'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('hidden_charts', 1);
        $chart = $this->scoped(fn () => HiddenChart::query()->sole());
        $this->assertSame($this->workspace->id, (int) $chart->workspace_id);

        $page = $this->withSession(['chart_hidden' => $chart->id])->get($this->url())->assertOk()
            ->assertSee('Commits chart hidden from this project.')->assertSee('Undo')->assertSee('1 chart hidden')->getContent();
        $this->assertSame(['prs_merged', 'loc_changed', 'open_issues'], $this->chartKeys($page));

        $this->from($this->url())->delete($this->url('/hidden-charts/'.$chart->id), ['return' => 'project'])
            ->assertRedirect($this->url().'#chart-'.$this->source->id.'-commits-label')->assertSessionHas('status', 'Commits chart is shown again.');
        $this->assertDatabaseCount('hidden_charts', 0);
        $this->assertSame(self::KEYS, $this->chartKeys($this->get($this->url())->assertDontSee('chart hidden')->getContent()));
    }

    public function test_hidden_charts_are_listed_and_restored_in_project_settings(): void
    {
        $this->actingAs($this->owner)->get($this->url('/settings'))->assertOk()->assertSee('Hidden charts')->assertSee('No hidden charts.');
        foreach (self::KEYS as $metric) {
            $this->from($this->url())->post($this->url('/hidden-charts'), ['source_id' => $this->source->id, 'metric_key' => $metric]);
        }
        $this->get($this->url())->assertOk()->assertSee('4 charts hidden')->assertSee('All charts from this data source are hidden.')->assertSee($this->url('/settings').'#hidden-charts');
        $this->get($this->url('/settings'))->assertOk()->assertSee('Commits')->assertSee('PRs merged')->assertSee('Show chart');

        $chart = $this->scoped(fn () => HiddenChart::query()->where('metric_key', 'prs_merged')->sole());
        $this->from($this->url('/settings'))->delete($this->url('/hidden-charts/'.$chart->id))->assertRedirect($this->url('/settings'))->assertSessionHas('status', 'PRs merged chart is shown again.');
        $this->assertSame(['prs_merged'], $this->chartKeys($this->get($this->url())->assertSee('3 charts hidden')->getContent()));
    }

    public function test_hiding_is_limited_to_the_projects_own_sources_and_known_metrics(): void
    {
        $other = $this->scoped(function () {
            $project = Project::create(['name' => 'Other', 'slug' => 'other']);

            return Source::create(['project_id' => $project->id, 'kind' => 'wporg', 'label' => 'Plugin', 'identity_hash' => str_repeat('d', 64), 'config' => ['slug' => 'other'], 'reporting_timezone' => 'UTC']);
        });
        $this->actingAs($this->owner)->post($this->url('/hidden-charts'), ['source_id' => $other->id, 'metric_key' => 'downloads'])->assertSessionHasErrors('source_id');
        $this->post($this->url('/hidden-charts'), ['source_id' => $this->source->id, 'metric_key' => 'unknown'])->assertSessionHasErrors('metric_key');
        $this->assertDatabaseCount('hidden_charts', 0);

        $outsider = User::factory()->create();
        Workspace::create(['owner_id' => $outsider->id, 'name' => 'Elsewhere', 'slug' => 'elsewhere']);
        $this->actingAs($outsider)->post($this->url('/hidden-charts'), ['source_id' => $this->source->id, 'metric_key' => 'commits'])->assertNotFound();
    }

    public function test_viewers_without_edit_access_see_no_hide_controls(): void
    {
        $this->from($this->url())->actingAs($this->owner)->post($this->url('/hidden-charts'), ['source_id' => $this->source->id, 'metric_key' => 'commits']);
        $chart = $this->scoped(fn () => HiddenChart::query()->sole());
        $this->app->instance(WorkspaceAccess::class, new class extends PersonalWorkspaceAccess
        {
            public function allows(Authenticatable $user, Workspace $workspace, string $ability): bool
            {
                return $ability !== 'edit' && parent::allows($user, $workspace, $ability);
            }
        });

        $this->get($this->url())->assertOk()->assertDontSee('Hide Commits chart')->assertDontSee('Hide PRs merged chart')->assertSee('1 chart hidden');
        $this->get($this->url('/settings'))->assertOk()->assertSee('Commits')->assertDontSee('Show chart');
        $this->post($this->url('/hidden-charts'), ['source_id' => $this->source->id, 'metric_key' => 'prs_merged'])->assertForbidden();
        $this->delete($this->url('/hidden-charts/'.$chart->id))->assertForbidden();
    }

    public function test_hidden_charts_do_not_change_the_overview_and_follow_their_data_source(): void
    {
        $this->from($this->url())->actingAs($this->owner)->post($this->url('/hidden-charts'), ['source_id' => $this->source->id, 'metric_key' => 'commits']);
        $this->get('/w/charts')->assertOk()->assertDontSee('chart hidden');
        $this->scoped(fn () => $this->source->delete());
        $this->assertDatabaseCount('hidden_charts', 0);
    }
}
