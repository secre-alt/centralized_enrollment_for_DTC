<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetApplicationLocale
{
    private const SUPPORTED_LOCALES = ['en', 'fil'];

    public function handle(Request $request, Closure $next)
    {
        $userLanguage = $request->user()?->preferred_language;
        $defaultLanguage = Setting::get('default_language', config('app.locale', 'en'));

        $locale = in_array($userLanguage, self::SUPPORTED_LOCALES, true)
            ? $userLanguage
            : (in_array($defaultLanguage, self::SUPPORTED_LOCALES, true) ? $defaultLanguage : 'en');

        App::setLocale($locale);

        return $next($request);
    }
}
