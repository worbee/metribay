<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Models\Workspace;
use Tests\TestCase;

class AccountPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_hosts_keep_account_and_preferences_by_default(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get('/account')->assertOk()->assertSee('Account &amp; preferences', false)->assertSee('data-number-preference', false)->assertSee('data-density-preference', false);
        $this->get('/workspaces')->assertSee('Account menu')->assertSee('Sign out');
    }

    public function test_hosts_can_hide_account_and_move_appearance_to_settings(): void
    {
        config(['metribay.account_enabled' => false]);
        $owner = User::factory()->create();
        Workspace::create(['owner_id' => $owner->id, 'name' => 'Local', 'slug' => 'local']);
        $this->actingAs($owner)->get('/account')->assertNotFound();
        $this->get('/workspaces')->assertOk()->assertDontSee('Account menu')->assertDontSee('Account &amp; preferences', false)->assertDontSee('Sign out');
        $this->get('/w/local/settings?tab=appearance')->assertOk()->assertSee('Color theme')->assertSee('data-number-preference', false)->assertSee('data-density-preference', false)->assertDontSee('Account menu')->assertSee('data-theme-cycle', false);
    }
}
