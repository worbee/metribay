<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrustedHostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // TrustHosts skips local/testing; exercise the actual production middleware.
        $this->app['env'] = 'production';
        config(['app.url' => 'https://metrics.example.test', 'app.debug' => false]);
        $this->withSession(['_token' => 'synthetic-csrf-token']);
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        TrustHosts::flushState();
        TrustProxies::flushState();
        parent::tearDown();
    }

    public static function invalidHosts(): array
    {
        return ['attacker' => ['attacker.example.test'], 'subdomain' => ['other.metrics.example.test'], 'suffix' => ['metrics.example.test.attacker.test']];
    }

    #[DataProvider('invalidHosts')]
    public function test_untrusted_hosts_cannot_generate_reset_mail(string $host): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('https://'.$host.'/forgot-password', ['email' => $user->email, '_token' => 'synthetic-csrf-token'])->assertBadRequest();

        Notification::assertNothingSent();
    }

    public function test_canonical_host_generates_a_valid_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('https://metrics.example.test/forgot-password', ['email' => $user->email, '_token' => 'synthetic-csrf-token'])->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertSame('metrics.example.test', parse_url($url, PHP_URL_HOST));
            $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));

            return true;
        });
    }

    public function test_configured_loopback_host_and_port_are_supported(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        $this->get('http://127.0.0.1:8000/up')->assertOk();
    }

    public function test_trusted_proxy_cannot_forward_an_untrusted_host(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        TrustProxies::at(['127.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(['X-Forwarded-Host' => 'attacker.example.test'])->postJson('https://metrics.example.test/forgot-password', ['email' => $user->email, '_token' => 'synthetic-csrf-token'])->assertBadRequest();

        Notification::assertNothingSent();
    }
}
