<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustHosts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\SeedsRoles;
use Tests\TestCase;

class LoginSecurityRemediationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['services.recaptcha.enabled' => false]);
    }

    public function test_valid_captcha_allows_login_and_uses_configured_secret(): void
    {
        $user = $this->makeStudent('valid-captcha@example.test');
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
        ]);
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true], 200),
        ]);

        $response = $this->post(route('login'), $this->loginData($user->email) + [
            'g-recaptcha-response' => 'valid-test-token',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($user);
        Http::assertSent(function (ClientRequest $request) {
            return $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
                && $request['secret'] === 'test-secret-key'
                && $request['response'] === 'valid-test-token';
        });
    }

    public function test_invalid_captcha_does_not_authenticate(): void
    {
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
        ]);
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false], 200),
        ]);

        $response = $this->from(route('login'))->post(route('login'), $this->loginData() + [
            'g-recaptcha-response' => 'invalid-test-token',
        ]);

        $response->assertRedirect(route('login'))->assertSessionHasErrors('captcha');
        $this->assertGuest();
    }

    public function test_captcha_provider_http_failure_fails_closed(): void
    {
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
        ]);
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['error' => 'private detail'], 503),
        ]);

        $response = $this->from(route('login'))->post(route('login'), $this->loginData() + [
            'g-recaptcha-response' => 'test-token',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('captcha')
            ->assertDontSee('private detail');
        $this->assertGuest();
    }

    public function test_malformed_captcha_provider_response_fails_closed(): void
    {
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
        ]);
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response('not-json', 200),
        ]);

        $response = $this->from(route('login'))->post(route('login'), $this->loginData() + [
            'g-recaptcha-response' => 'test-token',
        ]);

        $response->assertRedirect(route('login'))->assertSessionHasErrors('captcha');
        $this->assertGuest();
    }

    public function test_captcha_timeout_or_connection_exception_fails_closed(): void
    {
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
        ]);
        Http::fake(function () {
            throw new ConnectionException('simulated provider timeout');
        });

        $response = $this->from(route('login'))->post(route('login'), $this->loginData() + [
            'g-recaptcha-response' => 'test-token',
        ]);

        $response->assertRedirect(route('login'))->assertSessionHasErrors('captcha');
        $this->assertGuest();
    }

    public function test_missing_captcha_configuration_fails_closed_without_http_request(): void
    {
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => null,
        ]);
        Http::fake();

        $response = $this->from(route('login'))->post(route('login'), $this->loginData() + [
            'g-recaptcha-response' => 'test-token',
        ]);

        $response->assertRedirect(route('login'))->assertSessionHasErrors('captcha');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_captcha_disabled_in_testing_allows_login_without_provider_call(): void
    {
        $user = $this->makeStudent('local-login@example.test');
        config([
            'app.env' => 'testing',
            'services.recaptcha.enabled' => false,
        ]);
        Http::fake();

        $response = $this->post(route('login'), $this->loginData($user->email));

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($user);
        Http::assertNothingSent();
    }

    public function test_captcha_cannot_be_disabled_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config([
            'app.env' => 'production',
            'services.recaptcha.enabled' => false,
        ]);
        Http::fake();

        $response = $this->from(route('login'))->post(route('login'), $this->loginData() + [
            'g-recaptcha-response' => 'test-token',
        ]);

        $response->assertRedirect(route('login'))->assertSessionHasErrors('captcha');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_login_limits_normalized_identity_and_ip_without_disclosing_account_existence(): void
    {
        $email = 'Limit.Tester@example.test';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))
                ->post(route('login'), $this->loginData($email, 'wrong-password'))
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }

        $response = $this->from(route('login'))
            ->post(route('login'), $this->loginData(strtolower($email), 'wrong-password'));

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please wait a few minutes and try again.']);
        $this->assertGuest();
    }

    public function test_successful_authentication_clears_identity_bucket_but_keeps_ip_bucket(): void
    {
        $user = $this->makeStudent('reset-limit@example.test');
        $identityKey = 'login:identity:' . hash('sha256', strtolower($user->email) . '|127.0.0.1');
        $ipKey = 'login:ip:' . hash('sha256', '127.0.0.1');

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->from(route('login'))->post(route('login'), $this->loginData($user->email, 'wrong-password'));
        }

        $this->post(route('login'), $this->loginData($user->email));

        $this->assertSame(0, RateLimiter::attempts($identityKey));
        $this->assertGreaterThan(0, RateLimiter::attempts($ipKey));
    }

    public function test_ip_limit_cannot_be_evaded_by_rotating_login_identifiers(): void
    {
        config(['services.login_rate_limit.per_ip_attempts' => 3]);

        foreach (['one@example.test', 'two@example.test', 'three@example.test'] as $email) {
            $this->from(route('login'))
                ->post(route('login'), $this->loginData($email, 'wrong-password'))
                ->assertSessionHasErrors('email');
        }

        $this->from(route('login'))
            ->post(route('login'), $this->loginData('four@example.test', 'wrong-password'))
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please wait a few minutes and try again.']);
    }

    public function test_modal_error_fallbacks_do_not_insert_server_messages_into_html(): void
    {
        $paymentModal = file_get_contents(resource_path('views/student/enrollment/_payment-modal.blade.php'));
        $receiptModal = file_get_contents(resource_path('views/cashier/payments/_receipt-modal.blade.php'));

        $this->assertStringNotContainsString('xhr.responseJSON.message', $paymentModal);
        $this->assertStringNotContainsString('xhr.responseJSON.message', $receiptModal);
        $this->assertStringContainsString('$modalContent.html(errorMarkup);', $paymentModal);
        $this->assertStringContainsString('$modalContent.html(errorMarkup);', $receiptModal);
    }

    public function test_trusted_hosts_are_exact_and_include_configured_application_host(): void
    {
        config([
            'app.url' => 'https://portal.example.test',
            'app.trusted_hosts' => ['portal.example.test', 'www.example.test'],
        ]);

        $trustedHosts = (new TrustHosts(app()))->hosts();

        $this->assertContains('^portal\\.example\\.test$', $trustedHosts);
        $this->assertContains('^www\\.example\\.test$', $trustedHosts);
        $this->assertNotContains('^(.+\\.)?example\\.test$', $trustedHosts);
    }

    private function makeStudent(string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('correct-password'),
            'status' => 'active',
        ]);
        $user->assignRole('student');

        return $user;
    }

    private function loginData(string $email = 'unknown@example.test', string $password = 'correct-password'): array
    {
        return ['email' => $email, 'password' => $password];
    }
}
