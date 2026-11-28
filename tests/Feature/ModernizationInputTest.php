<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class ModernizationInputTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    public function test_password_link_pages_reject_array_email_without_a_server_error(): void
    {
        foreach (['password.reset', 'password.setup.show'] as $route) {
            $this->getJson(route($route, ['token' => 'invalid', 'email' => ['invalid']]))
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
    }

    public function test_password_reset_rejects_non_string_token_before_the_broker(): void
    {
        $user = User::factory()->create();
        $original = $user->password;
        $this->postJson(route('password.update'), ['token' => ['invalid'], 'email' => $user->email,
            'password' => 'PasswordExample!2026', 'password_confirmation' => 'PasswordExample!2026'])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_profile_password_rejects_array_current_password_and_preserves_credentials(): void
    {
        $this->seedRoles();
        $user = $this->makeUserWithRole('student');
        $original = $user->password;
        $this->actingAs($user)->postJson(route('portal.profile.password'), [
            'current_password' => ['invalid'], 'password' => 'PasswordExample!2026',
            'password_confirmation' => 'PasswordExample!2026',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_enrollment_help_explains_payment_verification_before_final_registrar_approval(): void
    {
        $this->seedRoles();
        $response = $this->actingAs($this->makeUserWithRole('student'))->get(route('help.index'))->assertOk();
        $answer = $response->viewData('topics')->firstWhere('title', 'Does application approval complete enrollment?')['answer'];
        $this->assertStringContainsString('assigned sections', $answer);
        $this->assertStringContainsString('Cashier verification', $answer);
        $this->assertStringContainsString('final Registrar approval', $answer);
        $this->assertLessThan(strpos($answer, 'final Registrar approval'), strpos($answer, 'Cashier verification'));
    }

    public function test_required_password_change_rejects_array_before_hash_validation(): void
    {
        $this->seedRoles();
        $user = $this->makeUserWithRole('student', ['must_change_password' => true]);
        $original = $user->password;
        $this->actingAs($user)->postJson(route('account.password.update'), [
            'current_password' => ['invalid'], 'password' => 'PasswordExample!2026',
            'password_confirmation' => 'PasswordExample!2026',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame($original, $user->fresh()->password);
        $this->assertTrue($user->fresh()->must_change_password);
    }
}
