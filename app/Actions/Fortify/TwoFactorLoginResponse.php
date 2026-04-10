<?php

namespace App\Actions\Fortify;

use App\Actions\Auth\SyncAuthenticatedUserLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        if (Auth::check()) {
            app(SyncAuthenticatedUserLocale::class)->execute($request, Auth::user());
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(Fortify::redirects('login'));
    }
}
