<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Count every submitted credential attempt before authentication.
        RateLimiter::hit($ipLimiterKey, $decaySeconds);
        RateLimiter::hit($identityLimiterKey, $decaySeconds);

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);
        $credentials['email'] = $email;

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

    public function confirmLogout()
    {
        if (! Auth::check()) {
            return redirect()->route('landing');
        }

        return response()->view('auth.confirm-logout')->header('Cache-Control', 'no-store, private');
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
