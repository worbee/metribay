<?php

namespace Metribay\Core\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Number;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\Locales;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_follow_the_browser_language_and_can_switch(): void
    {
        $this->get('/login')->assertOk()->assertSee('lang="en"', false)->assertSee('Welcome back.');
        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.5')->get('/login')->assertOk()->assertSee('lang="de"', false)->assertSee('Willkommen zurück.')->assertSee('Deutsch');
        $this->from('/login')->post('/locale', ['locale' => 'de'])->assertRedirect('/login');
        $this->withHeader('Accept-Language', 'en')->get('/login')->assertSee('Willkommen zurück.');
        $this->post('/locale', ['locale' => 'ja'])->assertSessionHasErrors('locale');
        $this->from('/login')->post('/locale', ['locale' => 'fr'])->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('lang="fr"', false)->assertSee('Bon retour.');
    }

    public function test_guests_arriving_with_a_language_keep_it_for_the_session(): void
    {
        $this->withHeader('Accept-Language', 'en')->get('/login?lang=fr')->assertOk()->assertSee('lang="fr"', false)->assertSessionHas(Locales::SESSION_KEY, 'fr');
        $this->withHeader('Accept-Language', 'en')->get('/login')->assertSee('lang="fr"', false);
        $this->get('/login?lang=ja')->assertSee('lang="fr"', false);
        $this->get('/login?lang=pl')->assertSee('lang="pl"', false);
    }

    public function test_query_language_does_not_override_a_signed_in_users_choice(): void
    {
        $owner = User::factory()->create(['locale' => 'de']);
        Workspace::create(['owner_id' => $owner->id, 'name' => 'Local', 'slug' => 'local']);
        $this->actingAs($owner)->get('/w/local?lang=fr')->assertOk()->assertSee('lang="de"', false)->assertSessionMissing(Locales::SESSION_KEY);
        $this->assertSame('de', $owner->fresh()->locale);
    }

    public function test_signed_in_users_keep_their_language_across_sessions_and_mail(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        Workspace::create(['owner_id' => $owner->id, 'name' => 'Local', 'slug' => 'local']);
        $this->actingAs($owner)->from('/profile')->post('/locale', ['locale' => 'de'])->assertRedirect('/profile');
        $this->assertSame('de', $owner->fresh()->locale);
        $this->assertSame('de', $owner->fresh()->preferredLocale());
        $this->flushSession();
        $this->actingAs($owner->fresh())->withHeader('Accept-Language', 'en')->get('/w/local')->assertOk()->assertSee('Projekte')->assertSee('Erstelle dein erstes Projekt');
        $this->get('/w/local/settings')->assertOk()->assertSee('Verbundene Konten');
        $this->post('/w/local/projects', ['name' => ''])->assertSessionHasErrors(['name' => 'Name ist erforderlich.']);
        $owner->fresh()->notify(new ResetPassword('synthetic-token'));
        Notification::assertSentTo($owner, ResetPassword::class, fn ($notification, $channels, $notifiable, $locale) => $locale === 'de');
    }

    public function test_numbers_and_dates_use_the_active_locale(): void
    {
        app()->setLocale('de');
        $this->assertSame('1.234,5', Number::format(1234.5, 1, locale: app()->getLocale()));
        $this->assertSame('Okt 2026', CarbonImmutable::parse('2026-10-30')->translatedFormat('M Y'));
        $this->assertSame('Ereignisse', __('Events'));
        app()->setLocale('en');
        $this->assertSame('Events', __('Events'));
    }

    public function test_offered_locales_resolve_interface_strings(): void
    {
        $expected = [
            'bg' => 'Събития',
            'cs' => 'Události',
            'de' => 'Ereignisse',
            'es' => 'Eventos',
            'fr' => 'Événements',
            'it' => 'Eventi',
            'nl' => 'Gebeurtenissen',
            'pl' => 'Wydarzenia',
            'sk' => 'Udalosti',
            'sl' => 'Dogodki',
            'sr' => 'Događaji',
        ];

        foreach ($expected as $locale => $events) {
            $this->assertArrayHasKey($locale, Locales::all());
            app()->setLocale($locale);
            $this->assertSame($events, __('Events'));
            $this->assertNotSame('Language', __('Language'));
        }

        app()->setLocale('en');
        $this->assertSame('Events', __('Events'));
    }
}
