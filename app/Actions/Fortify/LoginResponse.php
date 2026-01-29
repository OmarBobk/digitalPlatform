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
            $user = Auth::user();
            $user->update(['last_login_at' => now()]);

            activity()
                ->inLog('admin')
                ->event('user.login')
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at?->format('M d, Y H:i') ?? '—',
                    'phone' => $user->phone,
                ])
                ->log('User login');

            if ($user->hasAnyRole(['admin', 'supervisor'])) {
                activity()
                    ->inLog('admin')
                    ->event('admin.login')
                    ->performedOn($user)
                    ->causedBy($user)
                    ->withProperties([
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'is_active' => $user->is_active,
                        'email_verified_at' => $user->email_verified_at?->format('M d, Y H:i') ?? '—',
                        'phone' => $user->phone,
                    ])
                    ->log('Admin login');
            }
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->route('home');
    }
}
