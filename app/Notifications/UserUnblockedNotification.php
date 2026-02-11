<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;

class UserUnblockedNotification extends BaseNotification
{
    public static function fromUser(User $user): self
    {
        return new self(
            sourceType: User::class,
            sourceId: $user->id,
            title: __('notifications.user_unblocked_title'),
            message: __('notifications.user_unblocked_message'),
            url: null
        );
    }
}
