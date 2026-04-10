<?php

namespace App\Http\Middleware;

use App\Support\SupportedLocale;
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

        if (in_array($sessionLocale, SupportedLocale::ALLOWED, true)) {
            app()->setLocale($sessionLocale);
        } elseif (Auth::check()) {
            $userLocale = Auth::user()?->preferredLocale();

            if (in_array($userLocale, SupportedLocale::ALLOWED, true)) {
                app()->setLocale($userLocale);
                session()->put('locale', $userLocale);
            }
        } else {
            app()->setLocale(SupportedLocale::fromRequest($request));
        }

        return $next($request);
    }
}
