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
        $this->actingAs($owner)->get('/account')->assertRedirect('/profile');
        $this->get('/profile')->assertOk()->assertSee('Personal details')->assertSee('Profile &amp; preferences', false)->assertSee('data-number-preference', false)->assertSee('data-density-preference', false)->assertSee('name="locale"', false);
        $this->get('/profile')->assertSee('Profile menu')->assertSee('Sign out')->assertDontSee('Change workspace')->assertDontSee('Workspaces');
    }

    public function test_hosts_can_hide_account_and_move_appearance_to_settings(): void
    {
        config(['metribay.account_enabled' => false]);
        $owner = User::factory()->create();
        Workspace::create(['owner_id' => $owner->id, 'name' => 'Local', 'slug' => 'local']);
        $this->actingAs($owner)->get('/profile')->assertNotFound();
        $this->get('/w/local')->assertOk()->assertDontSee('Profile menu')->assertDontSee('Profile &amp; preferences', false)->assertDontSee('Sign out');
        $this->get('/w/local/settings?tab=appearance')->assertOk()->assertSee('Color theme')->assertSee('data-number-preference', false)->assertSee('data-density-preference', false)->assertDontSee('Profile menu')->assertSee('data-theme-cycle', false);
    }
}
