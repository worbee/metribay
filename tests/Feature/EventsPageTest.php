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
use Metribay\Core\Services\EventTimeline;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class EventsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Project $project;

    private Source $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Timeline', 'slug' => 'timeline']);
        $this->project = $this->scoped(fn () => Project::create(['name' => 'Tallybird', 'slug' => 'tallybird']));
        $this->repository = $this->scoped(fn () => Source::create(['project_id' => $this->project->id, 'kind' => 'github', 'label' => 'tallybird/app', 'identity_hash' => str_repeat('c', 64),
            'config' => ['repository' => 'tallybird/app', 'days' => 90], 'reporting_timezone' => 'UTC', 'last_success_at' => now()]));
    }

    private function scoped(callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($this->workspace, $callback);
    }

    private function event(array $values): ProjectEvent
    {
        return $this->scoped(fn () => ProjectEvent::create($values + ['project_id' => $this->project->id, 'title' => 'Event', 'origin' => 'manual']));
    }

    private function deployment(int $daysAgo, string $title, int $hour): ProjectEvent
    {
        $at = CarbonImmutable::now('UTC')->startOfDay()->subDays($daysAgo)->setTime($hour, 0);

        return $this->event(['date' => $at->toDateString(), 'occurred_at' => $at, 'title' => $title, 'origin' => 'github_deployment', 'external_id' => $title.$daysAgo.$hour, 'source_id' => $this->repository->id]);
    }

    private function page(string $query = ''): string
    {
        return $this->actingAs($this->owner)->get('/w/timeline/projects/'.$this->project->id.'/events'.$query)->assertOk()->getContent();
    }

    public function test_same_day_deployments_of_one_environment_collapse_into_a_bundle(): void
    {
        $this->deployment(2, 'production · v1.2.0', 9);
        $this->deployment(2, 'production · v1.2.1', 11);
        $this->deployment(2, 'production · v1.2.2', 15);
        $this->deployment(2, 'staging · a1b2c3d', 10);
        $this->event(['date' => now()->subDays(2)->toDateString(), 'title' => 'Pricing page launched', 'color' => 'green']);

        $html = $this->page();
        $this->assertStringContainsString('3 deployments · production', $html);
        $this->assertSame(1, substr_count($html, 'class="event-bundle"'));
        // The custom event leads its day; the single staging deployment stays a plain row.
        $day = substr($html, strpos($html, 'id="day-'.now()->subDays(2)->toDateString().'"'));
        $this->assertTrue(strpos($day, 'Pricing page launched') < strpos($day, '3 deployments'));
        $this->assertStringContainsString('staging · a1b2c3d', $day);
        // Newest first inside the bundle.
        $this->assertTrue(strpos($day, 'production · v1.2.2') < strpos($day, 'production · v1.2.0'));
    }

    public function test_repeating_events_show_once_in_upcoming_and_lightly_in_the_timeline(): void
    {
        $series = $this->event(['date' => now()->subDays(21)->toDateString(), 'title' => 'Weekly newsletter', 'recurrence' => 'weekly']);
        $ended = $this->event(['date' => now()->subDays(60)->toDateString(), 'title' => 'Old series', 'recurrence' => 'monthly', 'recurrence_until' => now()->subDays(40)->toDateString()]);
        $future = $this->event(['date' => now()->addDays(5)->toDateString(), 'title' => 'Launch day']);

        $html = $this->page();
        $upcoming = substr($html, strpos($html, 'id="upcoming-heading"'), strpos($html, 'id="timeline-heading"') - strpos($html, 'id="upcoming-heading"'));
        $this->assertStringContainsString('Launch day', $upcoming);
        $this->assertStringContainsString('Next: '.now()->addDays(7)->translatedFormat('j M Y'), $upcoming);
        $this->assertStringContainsString('Ended '.now()->subDays(40)->translatedFormat('j M Y'), $upcoming);
        $this->assertSame(1, substr_count($html, 'id="event-'.$series->id.'"'));
        // Occurrences 21, 14, 7 days ago and today: light entries that point to the series, not editable on their own.
        $this->assertSame(4, substr_count($html, 'id="occurrence-'.$series->id.'-'));
        $this->assertSame(1, substr_count($html, 'data-dialog-open="edit-event-'.$series->id.'"'));
        $this->assertSame(1, substr_count($html, 'id="edit-event-'.$series->id.'"'));
        $this->assertStringContainsString('id="edit-event-'.$ended->id.'"', $html);
        $this->assertStringContainsString('id="edit-event-'.$future->id.'"', $html);
        $this->assertStringNotContainsString('Launch day', substr($html, strpos($html, 'id="timeline-heading"'), strpos($html, 'id="new-event"') - strpos($html, 'id="timeline-heading"')));
    }

    public function test_filters_period_and_paging(): void
    {
        $this->deployment(3, 'production · v2', 9);
        $this->event(['date' => now()->subDays(4)->toDateString(), 'title' => 'Campaign start']);
        $this->event(['date' => now()->subDays(5)->toDateString(), 'title' => 'Server move', 'source_id' => $this->repository->id]);
        $this->event(['date' => now()->subDays(200)->toDateString(), 'title' => 'Ancient note']);

        $this->assertStringNotContainsString('production · v2', $this->page('?type=custom'));
        $this->assertStringNotContainsString('Campaign start', $this->page('?type=deployment'));
        $this->assertStringNotContainsString('Server move', $this->page('?source=project'));
        $this->assertStringNotContainsString('Campaign start', $this->page('?source='.$this->repository->id));
        $search = $this->page('?q=CAMPAIGN');
        $this->assertStringContainsString('Campaign start', $search);
        $this->assertStringNotContainsString('Server move', $search);
        $this->assertStringNotContainsString('Ancient note', $this->page('?days=90'));
        $this->assertStringContainsString('Ancient note', $this->page('?days=all'));
        $this->get('/w/timeline/projects/'.$this->project->id.'/events?days=14')->assertStatus(422);
        $this->get('/w/timeline/projects/'.$this->project->id.'/events?source=999')->assertSessionHasErrors('source');

        for ($i = 0; $i < EventTimeline::PER_PAGE; $i++) {
            $last = $this->event(['date' => now()->subDays(6 + intdiv($i, 3))->toDateString(), 'title' => 'Filler '.$i]);
        }
        $first = $this->page('?days=90');
        $this->assertStringContainsString('Page 1 of 2', $first);
        $this->assertStringNotContainsString('id="event-'.$last->id.'"', $first);
        // A chart marker's date opens the page that holds it.
        $this->assertStringContainsString('id="event-'.$last->id.'"', $this->page('?days=90&date='.now()->subDays(25)->toDateString()));
    }

    public function test_context_strip_shows_the_main_line_and_event_lanes(): void
    {
        $analytics = $this->scoped(function () {
            $source = Source::create(['project_id' => $this->project->id, 'kind' => 'umami', 'label' => 'Website', 'identity_hash' => str_repeat('d', 64), 'config' => ['website_id' => '11111111-1111-4111-8111-111111111111'], 'reporting_timezone' => 'UTC']);
            foreach ([1, 2, 4] as $daysAgo) {
                DailyMetric::create(['source_id' => $source->id, 'metric' => 'visits', 'date' => now()->subDays($daysAgo)->toDateString(), 'value' => 10 * $daysAgo, 'is_partial' => false, 'collected_at' => now()]);
            }

            return $source;
        });
        $this->deployment(1, 'production · v3', 9);
        $this->deployment(1, 'production · v4', 10);
        $release = $this->event(['date' => now()->subDays(3)->toDateString(), 'title' => 'v3.0.0', 'origin' => 'github_release', 'external_id' => 'r3', 'source_id' => $this->repository->id]);
        $this->event(['date' => now()->subDays(2)->toDateString(), 'title' => 'Sale', 'color' => 'amber']);

        $html = $this->page('?days=7');
        $this->assertSame(['Releases', 'Deployments', 'Custom'], array_map('html_entity_decode', $this->labels($html)));
        $this->assertStringContainsString('<small>'.$analytics->label.'</small>', $html);
        $this->assertStringContainsString('href="#event-'.$release->id.'"', $html);
        $this->assertStringContainsString('href="#day-'.now()->subDays(1)->toDateString().'"', $html);
        $this->assertStringContainsString('class="mark-count" aria-hidden="true">2<', $html);
        $this->assertMatchesRegularExpression('/<path d="M[0-9. LMh]+" vector-effect/', $html);
    }

    public function test_dialog_with_errors_reopens_and_quick_add_works(): void
    {
        $event = $this->event(['date' => now()->subDay()->toDateString(), 'title' => 'Typo']);
        $url = '/w/timeline/projects/'.$this->project->id.'/events';
        $this->actingAs($this->owner)->from($url)->patch($url.'/'.$event->id, ['_dialog' => 'edit-event-'.$event->id, 'date' => 'not a date', 'title' => 'Fixed'])->assertRedirect($url);
        $html = $this->get($url)->getContent();
        $this->assertMatchesRegularExpression('/id="edit-event-'.$event->id.'"\s+data-open-on-load/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="new-event"\s+data-open-on-load/', $html);
        // The edit form's old input stays in its own dialog.
        $this->assertStringNotContainsString('id="new-event-title" name="title" value="Fixed"', $html);

        $this->from($url)->post($url, ['_dialog' => 'quick-add', 'date' => now()->toDateString(), 'title' => 'Hotfix shipped'])->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertStringContainsString('Hotfix shipped', $this->get($url)->getContent());
    }

    /** @return list<string> */
    private function labels(string $html): array
    {
        preg_match_all('/<div class="strip-row"><span class="strip-label">([^<]+)<\/span>/', $html, $matches);

        return $matches[1];
    }
}
