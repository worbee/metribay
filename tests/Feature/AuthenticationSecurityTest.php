<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public static function sensitiveEndpoints(): array
    {
        return [
            'profile' => ['PUT', '/user/profile-information'],
            'password' => ['PUT', '/user/password'],
            'confirm password' => ['POST', '/user/confirm-password'],
            'enable MFA' => ['POST', '/user/two-factor-authentication'],
            'disable MFA' => ['DELETE', '/user/two-factor-authentication'],
            'QR code' => ['GET', '/user/two-factor-qr-code'],
            'recovery codes' => ['GET', '/user/two-factor-recovery-codes'],
            'verification resend' => ['POST', '/email/verification-notification'],
        ];
    }

    #[DataProvider('sensitiveEndpoints')]
    public function test_sensitive_endpoints_reject_stale_sessions(string $method, string $path): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->freezeTime();
        $session = [auth()->guard('web')->getName() => $user->id, 'password_hash_web' => Hash::make('old-password'), 'auth.password_confirmed_at' => now()->timestamp];

        $this->withSession($session)->json($method, $path, ['name' => 'Changed', 'email' => 'changed@example.test'])->assertUnauthorized();

        $this->assertSame($user->email, $user->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_password_change_invalidates_an_existing_login_session(): void
    {
        $user = User::factory()->create(['password' => 'synthetic-original-password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'synthetic-original-password'])->assertRedirect();
        $user->update(['password' => 'synthetic-reset-password']);
        auth()->forgetGuards();

        $this->putJson('/user/profile-information', ['name' => 'Changed', 'email' => 'changed@example.test'])->assertUnauthorized();

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public static function invalidPasswords(): array
    {
        return ['missing' => [[]], 'wrong' => [['current_password' => 'incorrect-password']]];
    }

    #[DataProvider('invalidPasswords')]
    public function test_email_change_requires_the_current_password(array $password): void
    {
        Notification::fake();
        $user = User::factory()->create(['name' => 'Original', 'email' => 'original@example.test', 'password' => 'synthetic-current-password']);

        $this->actingAs($user)->putJson('/user/profile-information', $password + ['name' => 'Changed', 'email' => 'changed@example.test'])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertSame('Original', $user->fresh()->name);
        $this->assertSame('original@example.test', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_valid_password_allows_email_change_and_requires_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'synthetic-current-password']);

        $this->actingAs($user)->putJson('/user/profile-information', ['name' => 'Changed', 'email' => ' CHANGED@example.test ', 'current_password' => 'synthetic-current-password'])->assertOk();

        $this->assertSame('changed@example.test', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unchanged_email_and_name_edits_do_not_require_a_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'original@example.test']);

        $this->actingAs($user)->putJson('/user/profile-information', ['name' => 'Changed', 'email' => ' ORIGINAL@example.test '])->assertOk();

        $this->assertSame('Changed', $user->fresh()->name);
        $this->assertSame('original@example.test', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_profile_shows_password_errors_without_flashing_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->put('/user/profile-information', ['name' => $user->name, 'email' => 'changed@example.test', 'current_password' => 'incorrect-password'])
            ->assertRedirect('/profile');

        $this->get('/profile')->assertOk()->assertSee('The password is incorrect.')->assertDontSee('value="incorrect-password"', false);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_password_update_keeps_the_current_session_valid(): void
    {
        $user = User::factory()->create(['password' => 'synthetic-current-password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'synthetic-current-password'])->assertRedirect();

        $this->putJson('/user/password', ['current_password' => 'synthetic-current-password', 'password' => 'synthetic-new-password', 'password_confirmation' => 'synthetic-new-password'])->assertOk();
        auth()->forgetGuards();

        $this->get('/profile')->assertOk();
        $this->assertTrue(Hash::check('synthetic-new-password', $user->fresh()->password));
    }
}
