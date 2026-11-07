<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdateProfileRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Show the profile settings page.
     */
    public function show()
    {
        $user           = Auth::user();
        $studentProfile = $user->studentProfile;

        return view('portal.profile.show', compact('user', 'studentProfile'))
            ->with('profileRoutePrefix', $this->routePrefix($user));
    }

    /**
     * Update personal information (name, email) and optional avatar.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user     = Auth::user();
        $fillable = $request->only(['name', 'email']);

        if ($request->hasFile('avatar')) {
            // Delete the old avatar before writing the new one.
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $fillable['avatar'] = $request->file('avatar')
                ->store('avatars', 'public');
        }

        $user->update($fillable);

        if ($user->wasChanged('email')) {
            return redirect()->route('verification.notice');
        }

        $redirectRoute = $request->boolean('return_to_dashboard')
            && $request->hasFile('avatar')
                ? match (true) {
                    $user->hasRole('admin') => 'admin.dashboard',
                    $user->hasRole('registrar') => 'registrar.dashboard',
                    $user->hasRole('cashier') => 'cashier.dashboard',
                    default => 'portal.dashboard',
                }
                : $this->routePrefix($user) . '.show';

        return redirect()->route($redirectRoute)
            ->with('success', __('ui.validation.profile_updated'));
    }

    /**
     * Update the user's preferred interface language.
     */
    public function updateLanguage(Request $request)
    {
        $validated = $request->validate([
            'preferred_language' => ['nullable', Rule::in(['en', 'fil'])],
        ], [
            'preferred_language.in' => __('language.invalid'),
        ]);

        $user = Auth::user();
        $user->preferred_language = $validated['preferred_language'] ?? null;
        $user->save();

        $defaultLanguage = Setting::get('default_language', config('app.locale', 'en'));
        $locale = $user->preferred_language;
        if (! in_array($locale, ['en', 'fil'], true)) {
            $locale = in_array($defaultLanguage, ['en', 'fil'], true) ? $defaultLanguage : 'en';
        }
        app()->setLocale($locale);

        return redirect()->route($this->routePrefix($user) . '.show')
            ->with('success', __('language.saved'));
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password'      => ['required'],
            'password'              => \App\Support\SecuritySettings::passwordRules(),
            'password_confirmation' => ['required'],
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => __('ui.validation.current_password_incorrect')])
                ->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        $user->update(['password' => Hash::make($request->password)]);
        $request->session()->regenerate();
        $request->session()->put('auth_session_token', $user->auth_session_token);

        return redirect()->route($this->routePrefix($user) . '.show')
            ->with('success', __('ui.validation.password_changed'));
    }

    /**
     * Remove the user's avatar and revert to the initials fallback.
     */
    public function removeAvatar()
    {
        $user = Auth::user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return redirect()->route($this->routePrefix($user) . '.show')
            ->with('success', __('ui.validation.photo_removed'));
    }

    /** Return the matching route namespace for portal and staff profiles. */
    private function routePrefix($user): string
    {
        return $user->hasAnyRole(['student', 'alumni', 'new_applicant'])
            ? 'portal.profile'
            : 'profile';
    }
}
