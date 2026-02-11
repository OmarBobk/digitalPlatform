<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;

class UserBlockedNotification extends BaseNotification
{
    public static function fromUser(User $user): self
    {
        return new self(
            sourceType: User::class,
            sourceId: $user->id,
            title: __('notifications.user_blocked_title'),
            message: __('notifications.user_blocked_message'),
            url: null
        );
    }
}
