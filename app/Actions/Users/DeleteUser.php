<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

class DeleteUser
{
    public function handle(User $user, int $causedById): void
    {
        $userId = $user->id;
        $email = $user->email;
        $username = $user->username;

        $user->delete();

        $causer = User::query()->find($causedById);
        activity()
            ->inLog('admin')
            ->event('user.deleted')
            ->causedBy($causer)
            ->withProperties([
                'user_id' => $userId,
                'email' => $email,
                'username' => $username,
            ])
            ->log('User deleted by admin');
    }
}
