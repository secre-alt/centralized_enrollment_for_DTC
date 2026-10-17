<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLanding()
    {
        return view('auth.welcome');
    }

    public function showLogin()
    {
        return view('auth.welcome');
    }

    public function login(Request $request)
    {
        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $ip = (string) ($request->ip() ?: 'unknown');
        $ipLimiterKey = 'login:ip:' . hash('sha256', $ip);
        $identityLimiterKey = 'login:identity:' . hash('sha256', $email . '|' . $ip);
        $ipLimit = (int) config('services.login_rate_limit.per_ip_attempts', 300);
        $identityLimit = (int) config('services.login_rate_limit.per_identity_attempts', 5);
        $decaySeconds = (int) config('services.login_rate_limit.decay_seconds', 300);

        if (RateLimiter::tooManyAttempts($ipLimiterKey, $ipLimit)
            || RateLimiter::tooManyAttempts($identityLimiterKey, $identityLimit)) {
            return back()
                ->withErrors(['email' => 'Too many login attempts. Please wait a few minutes and try again.'])
                ->onlyInput('email');
        }

        // Count attempts before CAPTCHA so provider failures cannot disable the limit.
        RateLimiter::hit($ipLimiterKey, $decaySeconds);
        RateLimiter::hit($identityLimiterKey, $decaySeconds);

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);
        $credentials['email'] = $email;

        $captchaEnabled = (bool) config('services.recaptcha.enabled', true);
        $nonProductionEnvironment = app()->environment(['local', 'testing']);

        // A disabled CAPTCHA is accepted only in local/testing. Production fails closed.
        if (! $captchaEnabled && ! $nonProductionEnvironment) {
            Log::error('Login CAPTCHA is disabled outside local/testing.');

            return $this->captchaRetryResponse();
        }

        if ($captchaEnabled) {
            $captchaToken = trim((string) $request->input('g-recaptcha-response', ''));
            $siteKey = trim((string) config('services.recaptcha.site_key', ''));
            $secretKey = trim((string) config('services.recaptcha.secret_key', ''));

            if ($captchaToken === '') {
                return back()
                    ->withErrors(['captcha' => 'Please complete the CAPTCHA and try again.'])
                    ->onlyInput('email');
            }

            if ($siteKey === '' || $secretKey === '') {
                Log::error('Login CAPTCHA is enabled but its site or secret key is missing.');

                return $this->captchaRetryResponse();
            }

            try {
                $verify = Http::connectTimeout(3)
                    ->timeout(5)
                    ->asForm()
                    ->post('https://www.google.com/recaptcha/api/siteverify', [
                        'secret' => $secretKey,
                        'response' => $captchaToken,
                        'remoteip' => $ip,
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('Login CAPTCHA provider connection failed.');

                return $this->captchaRetryResponse();
            } catch (\Throwable $exception) {
                Log::error('Login CAPTCHA verification raised an unexpected error.');

                return $this->captchaRetryResponse();
            }

            if (! $verify->successful()) {
                Log::warning('Login CAPTCHA provider returned an unsuccessful HTTP status.', [
                    'status' => $verify->status(),
                ]);

                return $this->captchaRetryResponse();
            }

            $captchaResult = $verify->json();
            if (! is_array($captchaResult) || ! array_key_exists('success', $captchaResult)) {
                Log::warning('Login CAPTCHA provider returned a malformed response.');

                return $this->captchaRetryResponse();
            }

            if ($captchaResult['success'] !== true) {
                Log::notice('Login CAPTCHA token was rejected.');

                return back()
                    ->withErrors(['captcha' => 'CAPTCHA verification failed. Please try again.'])
                    ->onlyInput('email');
            }
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

        // Keep the IP-wide bucket, but clear this normalized identity's failures.
        RateLimiter::clear($identityLimiterKey);

        // Prevent session fixation.
        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->status === 'locked' || $user->status === 'pending') {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Your account is ' . $user->status . '. Please contact the Administrator.',
            ]);
        }

        return redirect()->intended($this->redirectPath($user));
    }

    private function captchaRetryResponse()
    {
        return back()
            ->withErrors(['captcha' => 'CAPTCHA verification is temporarily unavailable. Please try again shortly.'])
            ->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }

    protected function redirectPath($user)
    {
        if ($user->hasRole('admin')) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('registrar')) {
            return route('registrar.dashboard');
        }

        if ($user->hasRole('cashier')) {
            return route('cashier.dashboard');
        }

        if ($user->hasAnyRole(['student', 'alumni', 'new_applicant'])) {
            return route('portal.dashboard');
        }

        return route('login');
    }
}
