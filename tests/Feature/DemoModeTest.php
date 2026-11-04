<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Metribay\Core\Http\Middleware\DemoMode;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\DemoData;
use Pdo\Sqlite;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => DemoData::EMAIL, 'locale' => 'en']);
        $workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'Samples', 'slug' => 'samples']);
        app(DemoData::class)->populate($workspace, days: 30);
    }

    public function test_demo_mode_is_off_unless_switched_on(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/locale', ['locale' => 'de'])->assertSessionMissing(DemoMode::NOTICE_KEY);
    }

    public function test_visitors_are_signed_in_as_the_sample_owner_and_see_the_banner(): void
    {
        config(['metribay.demo.enabled' => true]);
        $this->followingRedirects()->get('/')->assertOk()
            ->assertSee('This is a demo with fictional data. Changes are turned off.')
            ->assertSee('href="https://app.metribay.com/register"', false)
            ->assertSee('href="https://github.com/worbee/metribay"', false);
        $this->assertAuthenticatedAs($this->owner);
        $this->get('/login')->assertRedirect();
    }

    public function test_every_change_is_refused_before_it_reaches_a_controller(): void
    {
        config(['metribay.demo.enabled' => true]);
        $before = $this->counts();
        $checked = 0;
        /** @var Route $route */
        foreach (app('router')->getRoutes() as $route) {
            $methods = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']);
            $name = (string) $route->getName();
            if (! $methods || ! in_array('web', $route->gatherMiddleware(), true) || $name === 'metribay.locale' || str_ends_with($name, 'livewire.update') || str_starts_with($route->uri(), '_boost')) {
                continue;
            }
            $uri = '/'.ltrim((string) preg_replace('/\{[^}]+\}/', '1', $route->uri()), '/');
            $response = $this->from('/w/samples')->call(reset($methods), $uri, ['name' => 'Changed', 'email' => 'changed@example.test']);
            $this->assertTrue($response->isRedirect(url('/w/samples')), "{$name} ({$uri}) was not refused: HTTP {$response->getStatusCode()}");
            $response->assertSessionHas(DemoMode::NOTICE_KEY, 'Not available in the demo.');
            $checked++;
        }
        $this->assertGreaterThan(25, $checked);
        $this->assertSame($before, $this->counts());
        $this->deleteJson('/w/samples/projects/1')->assertForbidden()->assertJson(['message' => 'Not available in the demo.']);
        $this->get('/w/samples/sources/google')->assertRedirect()->assertSessionHas(DemoMode::NOTICE_KEY);
        $this->followingRedirects()->from('/w/samples')->post('/w/samples/projects', ['name' => 'Changed'])->assertSee('Not available in the demo.');
    }

    public function test_language_choices_stay_with_the_visitor(): void
    {
        config(['metribay.demo.enabled' => true]);
        $this->from('/w/samples')->post('/locale', ['locale' => 'de'])->assertRedirect('/w/samples')->assertSessionMissing(DemoMode::NOTICE_KEY);
        $this->assertSame('en', $this->owner->fresh()->locale);
        $this->get('/w/samples')->assertOk()->assertSee('Das ist eine Demo mit erfundenen Daten.');
        $this->get('/w/samples?lang=fr')->assertOk()->assertSee('Ceci est une démo avec des données fictives.');
    }

    public function test_usage_analytics_count_the_demo_as_its_own_edition(): void
    {
        config(['metribay.demo.enabled' => true, 'metribay.analytics.enabled' => true]);
        $this->get('/w/samples')->assertOk()->assertSee('"edition":"demo"', false);
    }

    public function test_a_read_only_sqlite_connection_refuses_writes(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'metribay-demo-');
        (new \PDO("sqlite:{$file}"))->exec('create table samples (value integer); insert into samples values (1);');
        config(['database.connections.demo_read_only' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true, 'journal_mode' => 'delete',
            'options' => [Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY]]]);
        try {
            $this->assertSame(1, DB::connection('demo_read_only')->table('samples')->count());
            $this->expectException(QueryException::class);
            DB::connection('demo_read_only')->table('samples')->insert(['value' => 2]);
        } finally {
            DB::purge('demo_read_only');
            @unlink($file);
        }
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $tables = ['users', 'workspaces', 'projects', 'sources', 'connections', 'project_events', 'hidden_charts', 'daily_metrics'];

        return array_combine($tables, array_map(fn (string $table): int => DB::table($table)->count(), $tables)) + ['locale' => (int) ($this->owner->fresh()->locale === 'en')];
    }
}
