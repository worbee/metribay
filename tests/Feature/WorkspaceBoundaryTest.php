<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectGroup;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\WorkspaceContext;
use Tests\TestCase;

class WorkspaceBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private $owner;

    private $other;

    private Workspace $workspace;

    private Workspace $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'First', 'slug' => 'first']);
        $this->foreign = Workspace::create(['owner_id' => $this->other->id, 'name' => 'Second', 'slug' => 'second']);
    }

    private function scoped(Workspace $workspace, callable $callback): mixed
    {
        return app(WorkspaceContext::class)->run($workspace, $callback(...));
    }

    public function test_queries_require_a_workspace_context(): void
    {
        $this->expectException(\LogicException::class);
        Project::query()->count();
    }

    public function test_context_is_cleared_even_on_failure(): void
    {
        try {
            $this->scoped($this->workspace, fn () => throw new \RuntimeException);
        } catch (\RuntimeException) {
        }$this->expectException(\LogicException::class);
        app(WorkspaceContext::class)->current();
    }

    public function test_foreign_workspace_is_not_disclosed(): void
    {
        $this->actingAs($this->owner)->get('/w/second')->assertNotFound();
    }

    public function test_reads_and_mutations_are_scoped_even_with_valid_foreign_ids(): void
    {
        $project = $this->scoped($this->foreign, fn () => Project::create(['name' => 'Private', 'slug' => 'private']));
        $this->actingAs($this->owner)->get('/w/first/projects/'.$project->id)->assertNotFound();
        $this->patchJson('/w/first/projects/'.$project->id, ['name' => 'Overwrite', 'version' => 1])->assertNotFound();
    }

    public function test_cross_workspace_group_is_rejected(): void
    {
        $group = $this->scoped($this->foreign, fn () => ProjectGroup::create(['name' => 'Private']));
        $this->actingAs($this->owner)->postJson('/w/first/projects', ['name' => 'Project', 'group_id' => $group->id])->assertUnprocessable();
    }

    public function test_project_and_group_workflow(): void
    {
        $this->actingAs($this->owner)->post('/w/first/groups', ['name' => 'Products'])->assertRedirect();
        $group = $this->scoped($this->workspace, fn () => ProjectGroup::first());
        $this->post('/w/first/projects', ['name' => 'Test project', 'domain' => 'https://example.com/', 'group_id' => $group->id])->assertRedirect();
        $project = $this->scoped($this->workspace, fn () => Project::first());
        $this->assertSame('example.com', $project->domain);
        $this->assertSame('test-project', $project->slug);
    }

    public function test_optimistic_update_preserves_newer_settings(): void
    {
        $project = $this->scoped($this->workspace, fn () => Project::create(['name' => 'Original', 'slug' => 'original']));
        $this->actingAs($this->owner)->patch('/w/first/projects/'.$project->id, ['name' => 'Updated', 'version' => 1])->assertRedirect();
        $this->patchJson('/w/first/projects/'.$project->id, ['name' => 'Stale', 'version' => 1])->assertConflict();
        $this->assertSame('Updated', $this->scoped($this->workspace, fn () => $project->fresh()->name));
    }

    public function test_connection_secret_is_encrypted_hidden_and_workspace_bound(): void
    {
        $this->actingAs($this->owner)->post('/w/first/connections', ['name' => 'Bing', 'kind' => 'bing', 'values' => ['api_key' => 'fixture-secret-only']])->assertRedirect();
        $connection = $this->scoped($this->workspace, fn () => Connection::first());
        $cipher = DB::table('connections')->where('id', $connection->id)->value('encrypted_payload');
        $this->assertStringNotContainsString('fixture-secret-only', $cipher);
        $this->assertArrayNotHasKey('encrypted_payload', $connection->toArray());
        $this->assertSame('fixture-secret-only', $this->scoped($this->workspace, fn () => $connection->reveal()['api_key']));
        DB::table('connections')->where('id', $connection->id)->update(['workspace_id' => $this->foreign->id]);
        $this->expectException(DecryptException::class);
        $this->scoped($this->foreign, fn () => Connection::first()->reveal());
    }

    public function test_configuration_export_never_contains_secrets(): void
    {
        $this->actingAs($this->owner)->post('/w/first/connections', ['name' => 'Bing', 'kind' => 'bing', 'values' => ['api_key' => 'fixture-secret-only']]);
        $this->get('/w/first/export')->assertOk()->assertDontSee('fixture-secret-only')->assertDontSee('encrypted_payload');
    }

    public function test_personal_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_event_foreign_source_cannot_be_attached(): void
    {
        $project = $this->scoped($this->workspace, fn () => Project::create(['name' => 'Own', 'slug' => 'own']));
        $source = $this->scoped($this->foreign, function () {
            $p = Project::create(['name' => 'Private', 'slug' => 'private']);

            return Source::create(['project_id' => $p->id, 'kind' => 'wporg', 'label' => 'Downloads', 'identity_hash' => str_repeat('a', 64), 'config' => ['slug' => 'example'], 'reporting_timezone' => 'UTC']);
        });
        $this->actingAs($this->owner)->postJson('/w/first/projects/'.$project->id.'/events', ['date' => '2026-10-25', 'title' => 'Note', 'source_id' => $source->id])->assertUnprocessable();
    }

    public function test_queued_payload_contains_ids_instead_of_models_or_secrets(): void
    {
        $serialized = serialize(new CollectSource($this->workspace->id, 123));
        $this->assertStringNotContainsString('fixture-secret', $serialized);
        $this->assertStringNotContainsString('encrypted_payload', $serialized);
        $this->assertSame($this->workspace->id.':123', (new CollectSource($this->workspace->id, 123))->uniqueId());
    }

    public function test_deleted_or_foreign_source_job_does_not_collect(): void
    {
        $this->scoped($this->foreign, function () {
            $p = Project::create(['name' => 'Private', 'slug' => 'private']);
            Source::create(['project_id' => $p->id, 'kind' => 'wporg', 'label' => 'Downloads', 'identity_hash' => str_repeat('a', 64), 'config' => ['slug' => 'example'], 'reporting_timezone' => 'UTC']);
        });
        Http::preventStrayRequests();
        app()->call([new CollectSource($this->workspace->id, 1), 'handle']);
        $this->assertSame(0, DB::table('sync_runs')->count());
    }
}
