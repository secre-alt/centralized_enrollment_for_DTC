<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class LanguagePreferenceTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_system_default_is_used_when_the_user_has_no_language_preference(): void
    {
        Setting::set('default_language', 'fil');
        $user = $this->makeUserWithRole('student');

        $this->actingAs($user)
            ->get(route('portal.profile.show'))
            ->assertOk()
            ->assertSee('<html lang="fil">', false)
            ->assertSee('Piliin ang wikang gagamitin sa iyong account.');
    }

    public function test_user_preference_overrides_the_system_default(): void
    {
        Setting::set('default_language', 'fil');
        $user = $this->makeUserWithRole('student', ['preferred_language' => 'en']);

        $this->actingAs($user)
            ->get(route('portal.profile.show'))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Choose the language used for your account.');
    }

    public function test_user_can_save_and_clear_a_supported_language_preference(): void
    {
        Setting::set('default_language', 'en');
        $user = $this->makeUserWithRole('student');

        $this->actingAs($user)
            ->post(route('portal.profile.language.update'), ['preferred_language' => 'fil'])
            ->assertRedirect(route('portal.profile.show'))
            ->assertSessionHas('success', 'Na-save ang iyong piniling wika.');

        $this->assertSame('fil', $user->fresh()->preferred_language);
        $user = $user->fresh();
        $this->actingAs($user)->get(route('portal.profile.show'))
            ->assertOk()
            ->assertSee('Piliin ang wikang gagamitin sa iyong account.');

        $this->post(route('portal.profile.language.update'), ['preferred_language' => ''])
            ->assertRedirect(route('portal.profile.show'));

        $this->assertNull($user->fresh()->preferred_language);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_unsupported_language_preferences_are_rejected(): void
    {
        Setting::set('default_language', 'fil');
        $user = $this->makeUserWithRole('student');

        $this->actingAs($user)
            ->from(route('portal.profile.show'))
            ->post(route('portal.profile.language.update'), ['preferred_language' => 'es'])
            ->assertRedirect(route('portal.profile.show'))
            ->assertSessionHasErrors(['preferred_language' => 'Pumili ng Ingles o Tagalog.']);

        $this->assertNull($user->fresh()->preferred_language);
    }

    public function test_staff_can_save_a_language_preference_from_their_profile(): void
    {
        $user = $this->makeUserWithRole('admin');

        $this->actingAs($user)
            ->post(route('profile.language.update'), ['preferred_language' => 'fil'])
            ->assertRedirect(route('profile.show'));

        $this->assertSame('fil', $user->fresh()->preferred_language);
        $this->actingAs($user->fresh())
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Piliin ang wikang gagamitin sa iyong account.');
    }

    public function test_invalid_system_default_falls_back_to_english(): void
    {
        Setting::set('default_language', 'es');
        $user = $this->makeUserWithRole('student');

        $this->actingAs($user)
            ->get(route('portal.profile.show'))
            ->assertOk()
            ->assertSee('Choose the language used for your account.');
    }

    public function test_unsupported_user_preference_falls_back_to_the_system_default(): void
    {
        Setting::set('default_language', 'fil');
        $user = $this->makeUserWithRole('student', ['preferred_language' => 'es']);

        $this->actingAs($user)
            ->get(route('portal.profile.show'))
            ->assertOk()
            ->assertSee('<html lang="fil">', false)
            ->assertSee('Piliin ang wikang gagamitin sa iyong account.');
    }

    public function test_public_homepage_and_authentication_forms_follow_the_language_setting(): void
    {
        Setting::set('default_language', 'fil');

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('<html lang="fil">', false)
            ->assertSee('Mag-sign in')
            ->assertSee('Mga Serbisyo ng DTC.')
            ->assertSee('Paano ako mag-e-enroll gamit ang DTC EMS?');

        $user = $this->makeUserWithRole('student');
        $this->actingAs($user)
            ->get(route('portal.profile.show'))
            ->assertOk()
            ->assertSee('Personal na Impormasyon')
            ->assertSee('Piliin ang wikang gagamitin sa iyong account.');

        auth()->logout();
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('<html lang="fil">', false)
            ->assertSee('Nakalimutan ang password?')
            ->assertSee('Ipadala ang link para i-reset');
    }

    public function test_role_navigation_is_translated_using_the_effective_locale(): void
    {
        Setting::set('default_language', 'fil');
        $user = $this->makeUserWithRole('admin');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('PANEL NG ADMIN')
            ->assertSee('Pamahalaan ang mga User')
            ->assertSee('Dashboard ng Admin')
            ->assertSee('Kabuuang Kita')
            ->assertSee('Mabilisang Aksyon');

        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)
            ->get(route('registrar.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard ng Registrar')
            ->assertSee('Mga Kamakailang Enrollment')
            ->assertSee('Mga Kamakailang Aplikasyon');

        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($cashier)
            ->get(route('cashier.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard ng Cashier')
            ->assertSee('Mga Kamakailang Bayad')
            ->assertSee('Buod Ngayong Araw');
    }
}
