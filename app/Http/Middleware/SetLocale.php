<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * Class SetLocale
 *
 * Resolves the request locale, in priority order:
 *   1. session('locale') — set by the locale switcher route
 *   2. the authenticated user's profile preference (userprofiles.locale)
 *   3. config('school.locale.default')
 *
 * Only locales declared in config('school.locale.available') are accepted.
 * Wire it into the `web` middleware group (app/Http/Kernel.php) during
 * integration so every portal page honours it; it is already applied to
 * the routes in routes/web.php.
 */
class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $available = array_keys(config('school.locale.available', ['en' => 'English']));

        $locale = $request->session()->get('locale');

        if (!$locale && Auth::check()) {
            $locale = optional(Auth::user()->userprofile)->locale;
        }

        if (!$locale || !in_array($locale, $available)) {
            $locale = config('school.locale.default', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
