<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

class UnblockUser
{
    public function handle(User $user, int $causedById): void
    {
        $user->forceFill(['blocked_at' => null])->save();

        $causer = User::query()->find($causedById);
        activity()
            ->inLog('admin')
            ->event('user.unblocked')
            ->performedOn($user)
            ->causedBy($causer)
            ->withProperties([
                'user_id' => $user->id,
                'email' => $user->email,
            ])
            ->log('User unblocked');
    }
}
