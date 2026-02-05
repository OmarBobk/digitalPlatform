<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

class VerifyUserEmail
{
    public function handle(User $user, int $causedById): void
    {
        $user->forceFill(['email_verified_at' => now()])->save();

        $causer = User::query()->find($causedById);
        activity()
            ->inLog('admin')
            ->event('user.email_verified')
            ->performedOn($user)
            ->causedBy($causer)
            ->withProperties([
                'user_id' => $user->id,
                'email' => $user->email,
            ])
            ->log('User email verified by admin');
    }
}
