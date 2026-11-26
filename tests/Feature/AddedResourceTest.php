<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\ProjectResources;
use Metribay\Core\Services\ProviderCatalog;
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
        // These sources are paused without an account; those that need one say so instead of only "already added".
        $paused = ProviderCatalog::requiresConnection($kind);
        $notice = $paused ? 'Already in this project and paused: it has no connected account.' : 'This data source is already in this project.';
        $html = $this->get('/w/resources/sources/new')->assertOk()->assertSee($notice)->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@data-resource-row]//button[@disabled]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-resource-row]//button[not(@disabled)]')->length);
        $this->post('/w/resources/sources/setup', ['action' => 'resource', 'resource' => 0])->assertSessionHasErrors('resource');
        $this->post('/w/resources/sources/setup', ['action' => 'manual', 'resource_id' => $listed, 'timezone' => 'UTC'])->assertSessionHasErrors(['resource' => $paused
            ? 'This data source is already in this project and is paused without a connected account. Choose an account for it under Manage data source in the project settings.'
            : 'This data source is already in this project.']);
        Http::assertNothingSent();
        $other = app(WorkspaceContext::class)->run($workspace, fn () => Project::create(['name' => 'Other', 'slug' => 'other']));
        $draft['project_id'] = $other->id;
        $this->withSession([$key => $draft])->get('/w/resources/sources/new')->assertOk()->assertDontSee($notice);
    }

    public function test_a_resource_paused_without_an_account_links_to_choosing_one(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Moved', 'slug' => 'moved']);
        [$project, $paused] = app(WorkspaceContext::class)->run($workspace, function () {
            $project = Project::create(['name' => 'Example', 'slug' => 'example']);
            $account = new Connection(['kind' => 'ga4', 'name' => 'Analytics']);
            $account->workspace_id = app(WorkspaceContext::class)->id();
            $account->storeSecrets(['service_account' => '{"type":"service_account"}']);
            $account->save();
            $paused = Source::create(['project_id' => $project->id, 'kind' => 'ga4', 'label' => 'Moved', 'config' => ['property_id' => '111'], 'reporting_timezone' => 'UTC', 'identity_hash' => str_repeat('a', 64), 'enabled' => false]);
            Source::create(['project_id' => $project->id, 'kind' => 'ga4', 'label' => 'Paused by hand', 'connection_id' => $account->id, 'config' => ['property_id' => '222'], 'reporting_timezone' => 'UTC', 'identity_hash' => str_repeat('b', 64), 'enabled' => false]);

            return [$project, $paused];
        });
        $key = 'source_setup.'.$owner->id.'.'.$workspace->id;
        $this->actingAs($owner)->withSession([$key => ['project_id' => $project->id, 'kind' => 'ga4', 'expires' => time() + 3600, 'step' => 'resources', 'listed' => true, 'resources' => [
            ['id' => '111', 'name' => 'Moved property', 'domain' => '', 'timezone' => 'UTC'],
            ['id' => '222', 'name' => 'Paused property', 'domain' => '', 'timezone' => 'UTC'],
        ]]]);

        $html = $this->get('/w/moved/sources/new')->assertOk()->assertSeeInOrder(['Moved property', 'Already in this project and paused: it has no connected account.', 'Paused property', 'This data source is already in this project.'])->getContent();
        $xpath = new \DOMXPath(tap(new \DOMDocument, fn (\DOMDocument $document) => @$document->loadHTML($html)));
        $links = $xpath->query('//form[@data-resource-row]//a[@href]');
        $this->assertSame(1, $links->length);
        $this->assertSame('/w/moved/projects/'.$project->id.'/settings#source-'.$paused->id, parse_url($links->item(0)->getAttribute('href'), PHP_URL_PATH).'#'.parse_url($links->item(0)->getAttribute('href'), PHP_URL_FRAGMENT));
        $this->assertSame('Choose an account →', trim($links->item(0)->textContent));
        $this->assertSame(2, $xpath->query('//form[@data-resource-row]//button[@disabled]')->length);
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
