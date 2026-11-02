<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\ProjectResources;
use Metribay\Core\Services\WorkspaceContext;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AddedResourceTest extends TestCase
{
    use RefreshDatabase;

    public static function providers(): array
    {
        return [['github', 'Worbee/Example', 'worbee/example'], ['wporg', 'example-plugin', 'example-plugin'], ['ga4', '12345', '12345'], ['gsc', 'sc-domain:example.test', 'sc-domain:example.test'], ['bing', 'https://example.test/', 'https://example.test/'], ['uptimerobot', '1234', '1234'], ['umami', '11111111-1111-4111-8111-111111111111', '11111111-1111-4111-8111-111111111111']];
    }

    #[DataProvider('providers')]
    public function test_added_resources_are_disabled_and_cannot_be_selected(string $kind, string $stored, string $listed): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Resources', 'slug' => 'resources']);
        $project = app(WorkspaceContext::class)->run($workspace, function () use ($kind, $stored) {
            $project = Project::create(['name' => 'Example', 'slug' => 'example']);
            Source::create(['project_id' => $project->id, 'kind' => $kind, 'label' => 'Added', 'config' => [ProjectResources::field($kind) => $stored], 'reporting_timezone' => 'UTC', 'identity_hash' => str_repeat('a', 64), 'enabled' => false]);

            return $project;
        });
        $key = 'source_setup.'.$owner->id.'.'.$workspace->id;
        $draft = ['project_id' => $project->id, 'kind' => $kind, 'expires' => time() + 3600, 'step' => 'resources', 'listed' => true, 'resources' => [
            ['id' => $listed, 'name' => 'Already added', 'domain' => '', 'timezone' => 'UTC'],
            ['id' => 'another-resource', 'name' => 'Available', 'domain' => '', 'timezone' => 'UTC'],
        ]];
        $this->actingAs($owner)->withSession([$key => $draft]);
        $html = $this->get('/w/resources/sources/new')->assertOk()->assertSee('This data source is already in this project.')->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@data-resource-row]//button[@disabled]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-resource-row]//button[not(@disabled)]')->length);
        $this->post('/w/resources/sources/setup', ['action' => 'resource', 'resource' => 0])->assertSessionHasErrors('resource');
        $this->post('/w/resources/sources/setup', ['action' => 'manual', 'resource_id' => $listed, 'timezone' => 'UTC'])->assertSessionHasErrors('resource');
        Http::assertNothingSent();
        $other = app(WorkspaceContext::class)->run($workspace, fn () => Project::create(['name' => 'Other', 'slug' => 'other']));
        $draft['project_id'] = $other->id;
        $this->withSession([$key => $draft])->get('/w/resources/sources/new')->assertOk()->assertDontSee('This data source is already in this project.');
    }

    public function test_confirmation_rechecks_a_resource_added_after_the_picker_was_shown(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Race', 'slug' => 'race']);
        $project = app(WorkspaceContext::class)->run($workspace, fn () => Project::create(['name' => 'Example', 'slug' => 'example']));
        $this->actingAs($owner)->get('/w/race/sources/new?project='.$project->id);
        $this->post('/w/race/sources/setup', ['action' => 'provider', 'kind' => 'wporg']);
        $this->post('/w/race/sources/setup', ['action' => 'manual', 'resource_id' => 'example-plugin', 'timezone' => 'UTC'])->assertSessionHasNoErrors();
        app(WorkspaceContext::class)->run($workspace, fn () => Source::create(['project_id' => $project->id, 'kind' => 'wporg', 'label' => 'Added concurrently', 'config' => ['slug' => 'example-plugin', 'limit' => 7], 'reporting_timezone' => 'Etc/UTC', 'identity_hash' => str_repeat('b', 64)]));
        $this->post('/w/race/sources/setup', ['action' => 'finish', 'days' => 180])->assertSessionHasErrors('resource');
        $this->assertDatabaseCount('sources', 1);
        Queue::assertNothingPushed();
    }
}
