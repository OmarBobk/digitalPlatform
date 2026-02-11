<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserBlockedNotification;
use Illuminate\Support\Facades\DB;

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

        $blockedUserId = $user->id;
        DB::afterCommit(function () use ($blockedUserId): void {
            $u = User::query()->find($blockedUserId);
            if ($u !== null) {
                $u->notify(UserBlockedNotification::fromUser($u));
            }
        });
    }
}
