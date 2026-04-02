<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionLocale = session()->get('locale');

        if (in_array($sessionLocale, ['en', 'ar'], true)) {
            app()->setLocale($sessionLocale);
        } elseif (Auth::check()) {
            $userLocale = Auth::user()?->preferredLocale();

            if (in_array($userLocale, ['en', 'ar'], true)) {
                app()->setLocale($userLocale);
                session()->put('locale', $userLocale);
            }
        }

        return $next($request);
    }
}
