<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Models\Workspace;
use Tests\TestCase;

class PersonalPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_opens_the_dashboard_without_workspace_controls(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Hidden personal scope', 'slug' => 'personal']);
        $this->actingAs($owner)->get('/')->assertRedirect('/w/personal');
        foreach (['/w/personal', '/account', '/w/personal/sources/new', '/w/personal/settings?tab=general', '/w/personal/settings?tab=connections', '/w/personal/settings?tab=data'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('Change workspace')->assertDontSee('Workspace name')->assertDontSee('Hidden personal scope')->assertDontSee('Workspaces');
        }
        $this->get('/workspaces')->assertNotFound();
        $this->post('/workspaces', ['name' => 'Another'])->assertNotFound();
        $this->patch('/w/personal/workspace', ['name' => 'Changed'])->assertNotFound();
        $this->assertSame('Hidden personal scope', $workspace->fresh()->name);
        $this->assertDatabaseCount('workspaces', 1);
    }

    public function test_missing_personal_scope_does_not_redirect_to_a_workspace_picker(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertNotFound();
    }
}
