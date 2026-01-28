<?php

namespace App\Actions\Fortify;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        // Update last login timestamp
        if (Auth::check()) {
            Auth::user()->update(['last_login_at' => now()]);
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->route('home');
    }
}
