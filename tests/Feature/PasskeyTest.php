<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_page_offers_a_passkey(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('data-passkey', false)
            ->assertSee(route('passkey.login-options'), false)
            ->assertSee('Sign in with a passkey')
            ->assertSee('autocomplete="username webauthn"', false);
    }

    public function test_a_passkey_signs_in_without_password_or_authenticator_code(): void
    {
        $user = User::factory()->create(['two_factor_secret' => encrypt('SYNTHETICSECRET1'), 'two_factor_confirmed_at' => now()]);
        $authenticator = new SoftwarePasskey;
        $authenticator->storeFor($user);

        $options = $this->getJson('/passkeys/login/options')->assertOk()->json('options');
        $this->assertSame([], $options['allowCredentials'] ?? [], 'Sign-in offers any discoverable passkey.');

        $this->postJson('/passkeys/login', ['credential' => $authenticator->assertion($options['challenge'], $user)])
            ->assertOk()->assertJsonPath('redirect', url('/'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('login.id'), 'No two-factor challenge is pending.');
        $this->assertNotNull($user->passkeys()->first()->last_used_at);
    }

    public function test_an_unknown_or_forged_passkey_does_not_sign_in(): void
    {
        $user = User::factory()->create();
        $stored = new SoftwarePasskey;
        $stored->storeFor($user);

        $options = $this->getJson('/passkeys/login/options')->json('options');
        $this->postJson('/passkeys/login', ['credential' => (new SoftwarePasskey)->assertion($options['challenge'], $user)])
            ->assertUnprocessable()->assertJsonValidationErrors('credential');
        $this->assertGuest();

        // A signature over another challenge (a replay) is rejected too.
        $this->getJson('/passkeys/login/options');
        $this->postJson('/passkeys/login', ['credential' => $stored->assertion(Base64UrlSafe::encodeUnpadded(random_bytes(32)), $user)])
            ->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_a_passkey_cannot_be_reused_without_fresh_options(): void
    {
        $user = User::factory()->create();
        $authenticator = new SoftwarePasskey;
        $authenticator->storeFor($user);
        $options = $this->getJson('/passkeys/login/options')->json('options');
        $credential = $authenticator->assertion($options['challenge'], $user);
        $this->postJson('/passkeys/login', ['credential' => $credential])->assertOk();
        auth()->logout();

        $this->postJson('/passkeys/login', ['credential' => $credential])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_profile_lists_passkeys_and_requires_a_confirmed_password_to_change_them(): void
    {
        $user = User::factory()->create();
        $user->passkeys()->create(['name' => 'Synthetic laptop', 'credential_id' => 'synthetic-id', 'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000']]);

        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSee('Passkeys')->assertSee('Synthetic laptop')
            ->assertSee('Confirm your password to add or remove passkeys.')
            ->assertDontSee(route('passkey.registration-options'), false);
        $this->getJson('/user/passkeys/options')->assertStatus(423);

        $this->withSession(['auth.password_confirmed_at' => time()])->get('/profile')->assertOk()
            ->assertSee(route('passkey.registration-options'), false)
            ->assertSee('Add a passkey')->assertSee('Remove');
    }

    public function test_a_confirmed_user_registers_a_passkey_and_can_then_sign_in_with_it(): void
    {
        $user = User::factory()->create();
        $authenticator = new SoftwarePasskey;
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        $options = $this->getJson('/user/passkeys/options')->assertOk()->json('options');
        $this->assertSame('required', $options['authenticatorSelection']['userVerification']);
        $this->postJson('/user/passkeys', ['name' => 'Synthetic Touch ID', 'credential' => $authenticator->attestation($options)])
            ->assertOk()->assertJsonPath('name', 'Synthetic Touch ID');
        $this->assertSame('passkey-registered', session('status'));
        $this->assertSame(['Synthetic Touch ID'], $user->passkeys()->pluck('name')->all());

        auth()->logout();
        $login = $this->getJson('/passkeys/login/options')->json('options');
        $this->postJson('/passkeys/login', ['credential' => $authenticator->assertion($login['challenge'], $user)])->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_registration_from_another_site_is_refused(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
        $options = $this->getJson('/user/passkeys/options')->json('options');
        $phishing = new SoftwarePasskey('https://phishing.example.test');

        $this->postJson('/user/passkeys', ['name' => 'Synthetic', 'credential' => $phishing->attestation($options)])
            ->assertUnprocessable()->assertJsonValidationErrors('credential');
        $this->assertSame(0, $user->passkeys()->count());
    }

    public function test_users_remove_only_their_own_passkeys(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = $user->passkeys()->create(['name' => 'Mine', 'credential_id' => 'synthetic-mine', 'credential' => []]);
        $theirs = $other->passkeys()->create(['name' => 'Theirs', 'credential_id' => 'synthetic-theirs', 'credential' => []]);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        $this->delete(route('passkey.destroy', $theirs))->assertForbidden();
        $this->from('/profile')->delete(route('passkey.destroy', $mine))->assertRedirect('/profile')->assertSessionHas('status', 'passkey-deleted');

        $this->assertModelMissing($mine);
        $this->assertModelExists($theirs);
    }

    public function test_a_passkey_confirms_the_password_for_its_owner_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = new SoftwarePasskey;
        $mine->storeFor($user);
        $theirs = new SoftwarePasskey;
        $theirs->storeFor($other);
        $this->actingAs($user);

        $this->get('/user/confirm-password')->assertOk()->assertSee('Use a passkey');
        $options = $this->getJson('/passkeys/confirm/options')->json('options');
        $this->assertCount(1, $options['allowCredentials']);
        $this->postJson('/passkeys/confirm', ['credential' => $theirs->assertion($options['challenge'], $other)])->assertUnprocessable();
        $this->assertNull(session('auth.password_confirmed_at'));

        $options = $this->getJson('/passkeys/confirm/options')->json('options');
        $this->postJson('/passkeys/confirm', ['credential' => $mine->assertion($options['challenge'], $user)])->assertOk();
        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_confirm_password_page_hides_passkeys_for_users_without_one(): void
    {
        $this->actingAs(User::factory()->create())->get('/user/confirm-password')->assertOk()->assertDontSee('Use a passkey');
    }

    public function test_deleting_a_user_deletes_their_passkeys(): void
    {
        $user = User::factory()->create();
        $passkey = $user->passkeys()->create(['name' => 'Synthetic', 'credential_id' => 'synthetic-cascade', 'credential' => []]);

        $user->delete();

        $this->assertModelMissing($passkey);
    }
}

/** A synthetic ES256 authenticator that produces the same WebAuthn payloads a browser sends. */
final class SoftwarePasskey
{
    public readonly string $credentialId;

    private \OpenSSLAsymmetricKey $key;

    public function __construct(private ?string $origin = null)
    {
        $this->credentialId = random_bytes(16);
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    }

    public function storeFor(User $user): Passkey
    {
        $record = CredentialRecord::create($this->credentialId, 'public-key', [], 'none', EmptyTrustPath::create(),
            Uuid::fromString('00000000-0000-0000-0000-000000000000'), $this->coseKey(), $user->getPasskeyUserHandle(), 0);

        return $user->passkeys()->create(['name' => 'Synthetic passkey', 'credential_id' => Base64UrlSafe::encodeUnpadded($this->credentialId),
            'credential' => json_decode(WebAuthn::toJson($record), true, flags: JSON_THROW_ON_ERROR)]);
    }

    /** @return array<string, mixed> */
    public function assertion(string $challenge, User $user): array
    {
        $clientData = $this->clientData('webauthn.get', $challenge);
        $authenticatorData = hash('sha256', (string) config('passkeys.relying_party_id'), true).chr(0x05).pack('N', time());
        openssl_sign($authenticatorData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return $this->credential(['clientDataJSON' => $clientData, 'authenticatorData' => $authenticatorData, 'signature' => $signature, 'userHandle' => $user->getPasskeyUserHandle()]);
    }

    /** @param  array<string, mixed>  $options  @return array<string, mixed> */
    public function attestation(array $options): array
    {
        $authData = hash('sha256', (string) config('passkeys.relying_party_id'), true).chr(0x45).pack('N', 0)
            .str_repeat("\0", 16).pack('n', strlen($this->credentialId)).$this->credentialId.$this->coseKey();
        // CBOR map {"fmt": "none", "attStmt": {}, "authData": bytes}.
        $attestation = "\xa3\x63fmt\x64none\x67attStmt\xa0\x68authData\x59".pack('n', strlen($authData)).$authData;

        return $this->credential(['clientDataJSON' => $this->clientData('webauthn.create', $options['challenge']), 'attestationObject' => $attestation]);
    }

    private function clientData(string $type, string $challenge): string
    {
        return json_encode(['type' => $type, 'challenge' => $challenge, 'origin' => $this->origin ?? config('passkeys.allowed_origins')[0], 'crossOrigin' => false], JSON_UNESCAPED_SLASHES);
    }

    /** COSE EC2 public key: {1: 2, 3: -7, -1: 1, -2: x, -3: y}. */
    private function coseKey(): string
    {
        $ec = openssl_pkey_get_details($this->key)['ec'];

        return "\xa5\x01\x02\x03\x26\x20\x01\x21\x58\x20".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT)."\x22\x58\x20".str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    }

    /** @param  array<string, string>  $response  @return array<string, mixed> */
    private function credential(array $response): array
    {
        $id = Base64UrlSafe::encodeUnpadded($this->credentialId);

        return ['id' => $id, 'rawId' => $id, 'type' => 'public-key', 'response' => array_map(Base64UrlSafe::encodeUnpadded(...), $response)];
    }
}
