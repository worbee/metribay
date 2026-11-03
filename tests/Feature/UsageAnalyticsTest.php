<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Metribay\Core\Models\Workspace;
use Tests\TestCase;

class UsageAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_pages_offer_opt_in_without_a_remote_script_tag_or_private_path(): void
    {
        $user = User::factory()->create();
        Workspace::create(['owner_id' => $user->id, 'name' => 'Private name', 'slug' => 'private-slug']);
        $response = $this->actingAs($user)->get('/w/private-slug?token=private-token');
        $response->assertOk()->assertSee('id="metribay-analytics"', false)->assertSee('data-cc="show-preferencesModal"', false);
        preg_match('~<script type="application/json" id="metribay-analytics">(.*?)</script>~s', $response->getContent(), $matches);
        $config = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('overview', $config['page']);
        $this->assertSame('personal', $config['edition']);
        $this->assertStringNotContainsString('private-slug', $matches[1]);
        $this->assertStringNotContainsString('private-token', $matches[1]);
        $response->assertDontSee('src="https://s.worbee.com/script.js"', false);
    }

    public function test_global_disable_and_sensitive_pages_do_not_initialize_analytics(): void
    {
        $this->get('/forgot-password')->assertOk()->assertDontSee('id="metribay-analytics"', false);
        config(['metribay.analytics.enabled' => false]);
        $this->get('/login')->assertOk()->assertDontSee('id="metribay-analytics"', false)->assertDontSee('data-cc="show-preferencesModal"', false);
    }

    public function test_only_known_conversion_markers_are_rendered(): void
    {
        $response = $this->withSession(['analytics_event' => 'signup_completed'])->get('/login');
        $response->assertSee('"event":"signup_completed"', false);
        $this->withSession(['analytics_event' => 'private-name'])->get('/login')->assertDontSee('private-name');
    }
}
