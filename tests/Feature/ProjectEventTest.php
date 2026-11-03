<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class ProjectEventTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Events', 'slug' => 'events']);
        $this->project = $this->scoped(fn () => Project::create(['name' => 'Example', 'slug' => 'example']));
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback);
    }

    private function event(array $values): ProjectEvent
    {
        return $this->scoped(fn () => ProjectEvent::create($values + ['project_id' => $this->project->id, 'title' => 'Event', 'origin' => 'manual']));
    }

    public function test_custom_events_store_color_and_recurrence(): void
    {
        $this->actingAs($this->owner)->post('/w/events/projects/'.$this->project->id.'/events', ['date' => '2027-01-15', 'title' => 'Monthly newsletter', 'color' => 'amber', 'recurrence' => 'monthly', 'recurrence_until' => '2027-06-30'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('project_events', ['title' => 'Monthly newsletter', 'color' => 'amber', 'recurrence' => 'monthly', 'origin' => 'manual']);
        $this->post('/w/events/projects/'.$this->project->id.'/events', ['date' => '2026-10-01', 'title' => 'One-off', 'recurrence_until' => '2026-12-01'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('project_events', ['title' => 'One-off', 'color' => 'blue', 'recurrence' => null, 'recurrence_until' => null]);
        $this->post('/w/events/projects/'.$this->project->id.'/events', ['date' => '2026-10-01', 'title' => 'Bad', 'color' => 'red'])->assertSessionHasErrors('color');
        $this->post('/w/events/projects/'.$this->project->id.'/events', ['date' => '2026-10-01', 'title' => 'Bad', 'recurrence' => 'daily'])->assertSessionHasErrors('recurrence');
        $this->post('/w/events/projects/'.$this->project->id.'/events', ['date' => '2026-10-10', 'title' => 'Bad', 'recurrence' => 'weekly', 'recurrence_until' => '2026-10-01'])->assertSessionHasErrors('recurrence_until');
        $this->get('/w/events/projects/'.$this->project->id.'/events')->assertOk()->assertSee('Events')->assertSee('Monthly newsletter')->assertSee('Repeats monthly')->assertSee('Upcoming')->assertDontSee('Annotation');
    }

    public function test_add_event_sits_beside_add_data_source(): void
    {
        $html = $this->actingAs($this->owner)->get('/w/events/projects/'.$this->project->id)->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-dialog-open="new-event"'));
        $heading = substr($html, strpos($html, 'class="project-actions"'), strpos($html, '</header>', strpos($html, 'class="project-actions"')) - strpos($html, 'class="project-actions"'));
        $this->assertStringContainsString('Add data source', $heading);
        $this->assertStringContainsString('data-dialog-open="new-event"', $heading);
        // The list moved to the events page; the header links there with the period's count.
        $this->assertStringContainsString('href="'.route('metribay.events.index', ['workspace' => 'events', 'project' => $this->project->id, 'days' => 30]).'"', $heading);
        $this->assertStringNotContainsString('event-entries', $html);
    }

    public function test_events_link_counts_markers_in_the_period(): void
    {
        $this->event(['date' => now()->subDays(2)->toDateString(), 'title' => 'Recent']);
        $this->event(['date' => now()->subDays(20)->toDateString(), 'title' => 'Weekly', 'recurrence' => 'weekly']);
        $this->event(['date' => now()->subDays(40)->toDateString(), 'title' => 'Old']);
        $this->event(['date' => now()->addDays(3)->toDateString(), 'title' => 'Future']);
        // Recent, plus the weekly event's occurrences 20, 13 and 6 days ago.
        $this->actingAs($this->owner)->get('/w/events/projects/'.$this->project->id)->assertOk()->assertSee('Events <span class="button-count">4</span>', false);
        $this->get('/w/events/projects/'.$this->project->id.'?days=7')->assertSee('Events <span class="button-count">2</span>', false);
    }

    public function test_occurrences_expand_within_a_window_without_drifting(): void
    {
        $monthly = $this->event(['date' => '2026-01-31', 'recurrence' => 'monthly']);
        $window = $monthly->occurrencesBetween(CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-05-31'));
        $this->assertSame(['2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31'], $window);
        $weekly = $this->event(['date' => '2010-01-04', 'recurrence' => 'weekly', 'recurrence_until' => '2026-10-19']);
        $this->assertSame(['2026-10-12', '2026-10-19'], $weekly->occurrencesBetween(CarbonImmutable::parse('2026-10-10'), CarbonImmutable::parse('2026-10-31')));
        $yearly = $this->event(['date' => '2024-02-29', 'recurrence' => 'yearly']);
        $this->assertSame(['2025-02-28', '2026-02-28'], $yearly->occurrencesBetween(CarbonImmutable::parse('2024-03-01'), CarbonImmutable::parse('2026-12-31')));
        $once = $this->event(['date' => '2026-10-20']);
        $this->assertSame([], $once->occurrencesBetween(CarbonImmutable::parse('2026-10-21'), CarbonImmutable::parse('2026-10-31')));
        $this->assertSame('2026-11-30', $monthly->nextOccurrence(CarbonImmutable::parse('2026-11-01')));
    }

    public function test_automatic_events_are_distinct_and_read_only(): void
    {
        $source = $this->scoped(fn () => Source::create(['project_id' => $this->project->id, 'kind' => 'github', 'label' => 'Repository', 'identity_hash' => str_repeat('b', 64), 'config' => ['repository' => 'example/app', 'days' => 30], 'reporting_timezone' => 'UTC']));
        $release = $this->event(['date' => now()->subDay()->toDateString(), 'title' => 'v1.0.0', 'origin' => 'github_release', 'external_id' => 'r1', 'source_id' => $source->id, 'recurrence' => 'weekly', 'color' => 'violet']);
        $this->assertSame('automatic', $release->displayColor());
        $this->assertCount(1, $release->occurrencesBetween(now()->subDays(30)->toImmutable(), now()->toImmutable()));
        $this->actingAs($this->owner)->patch('/w/events/projects/'.$this->project->id.'/events/'.$release->id, ['date' => '2026-10-01', 'title' => 'Changed'])->assertForbidden();
        $this->delete('/w/events/projects/'.$this->project->id.'/events/'.$release->id)->assertForbidden();
        $this->get('/w/events/projects/'.$this->project->id.'/events')->assertOk()->assertSee('Imported from GitHub')->assertSee('class="event-diamond"', false)
            ->assertDontSee('data-dialog-open="edit-event-'.$release->id.'"', false);
    }

    public function test_charts_receive_markers_for_their_project_and_data_source(): void
    {
        [$first, $second] = $this->scoped(function () {
            $make = fn (string $slug) => Source::create(['project_id' => $this->project->id, 'kind' => 'wporg', 'label' => 'Plugin '.$slug, 'identity_hash' => hash('sha256', $slug), 'config' => ['slug' => $slug, 'limit' => 30], 'reporting_timezone' => 'UTC', 'last_success_at' => now()]);
            $sources = [$make('first'), $make('second')];
            foreach ($sources as $source) {
                DailyMetric::create(['source_id' => $source->id, 'metric' => 'downloads', 'date' => now()->subDays(2)->toDateString(), 'value' => 5, 'is_partial' => false, 'collected_at' => now()]);
            }

            return $sources;
        });
        $this->event(['date' => now()->subDays(3)->toDateString(), 'title' => 'Whole project launch', 'color' => 'green']);
        $this->event(['date' => now()->subDays(4)->toDateString(), 'title' => 'Second only', 'source_id' => $second->id]);
        $this->event(['date' => now()->addDays(10)->toDateString(), 'title' => 'Future plan']);
        $html = $this->actingAs($this->owner)->get('/w/events/projects/'.$this->project->id.'?days=7')->assertOk()->getContent();
        preg_match_all('/data-chart-config="([^"]+)"/', $html, $matches);
        $configs = array_map(fn ($value) => json_decode(html_entity_decode($value), true), $matches[1]);
        $titles = array_map(fn ($config) => array_column($config['events'], 'title'), $configs);
        $this->assertContains(['Whole project launch'], $titles);
        $this->assertContains(['Second only', 'Whole project launch'], $titles);
        $this->assertNotContains('Future plan', array_merge(...$titles));
        $launch = collect($configs)->flatMap(fn ($config) => $config['events'])->firstWhere('title', 'Whole project launch');
        $this->assertSame('green', $launch['color']);
        // Markers open their event on the events page.
        $this->assertStringStartsWith('event-', $launch['anchor']);
        $this->assertStringEndsWith('/w/events/projects/'.$this->project->id.'/events?days=7', $configs[0]['eventsUrl']);
        $this->assertNotSame($first->id, $second->id);
    }
}
