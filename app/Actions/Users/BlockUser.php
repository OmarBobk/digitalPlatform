<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

class BlockUser
{
    public function handle(User $user, int $causedById): void
    {
        $user->forceFill(['blocked_at' => now()])->save();

        $causer = User::query()->find($causedById);
        activity()
            ->inLog('admin')
            ->event('user.blocked')
            ->performedOn($user)
            ->causedBy($causer)
            ->withProperties([
                'user_id' => $user->id,
                'email' => $user->email,
            ])
            ->log('User blocked');
    }
}
