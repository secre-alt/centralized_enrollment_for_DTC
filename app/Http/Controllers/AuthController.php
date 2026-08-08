<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        // ── Validate login credentials ────────────────────────────────
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // ── reCAPTCHA ─────────────────────────────────────────────────
        // Set RECAPTCHA_ENABLED=false in .env to temporarily disable it.
        if (config('services.recaptcha.enabled', true)) {

            $recaptcha = $request->input('g-recaptcha-response');

            // Make sure the user completed the CAPTCHA
            if (!$recaptcha) {
                return back()
                    ->withErrors([
                        'captcha' => 'Please complete the reCAPTCHA verification.'
                    ])
                    ->onlyInput('email');
            }

            // Verify CAPTCHA with Google
            try {
                $verify = Http::timeout(5)
                    ->asForm()
                    ->post(
                        'https://www.google.com/recaptcha/api/siteverify',
                        [
                            'secret'   => config('services.recaptcha.secret_key'),
                            'response' => $recaptcha,
                            'remoteip' => $request->ip(),
                        ]
                    );

                if (!$verify->successful() || !$verify->json('success')) {
                    Log::warning('reCAPTCHA verification failed.', [
                        'response' => $verify->json(),
                    ]);

                    return back()
                        ->withErrors([
                            'captcha' => 'reCAPTCHA verification failed. Please try again.'
                        ])
                        ->onlyInput('email');
                }

            } catch (\Exception $e) {

                Log::error('reCAPTCHA request failed.', [
                    'message' => $e->getMessage(),
                ]);

                // Allow login if Google's server cannot be reached.
                // Change this to return an error if you want fail-closed behavior.
                Log::warning('Login allowed because reCAPTCHA verification could not be completed.');
            }
        }

        // ── Authenticate ──────────────────────────────────────────────
        if (!Auth::attempt(
            $credentials,
            $request->boolean('remember')
        )) {
            return back()
                ->withErrors([
                    'email' => 'Invalid email or password.'
                ])
                ->onlyInput('email');
        }

        // Prevent session fixation
        $request->session()->regenerate();

        $user = Auth::user();

        // ── Check account status ──────────────────────────────────────
        if ($user->status === 'locked' || $user->status === 'pending') {

            Auth::logout();

            return back()->withErrors([
                'email' => 'Your account is ' .
                    $user->status .
                    '. Please contact the Administrator.',
            ]);
        }

        // ── Redirect according to role ────────────────────────────────
        return redirect()->intended(
            $this->redirectPath($user)
        );
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

        if ($user->hasAnyRole([
            'student',
            'alumni',
            'new_applicant'
        ])) {
            return route('portal.dashboard');
        }

        return route('login');
    }
}