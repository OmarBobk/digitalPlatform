<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class LoyaltyTierChangedNotification extends BaseNotification
{
    public static function fromUser(User $user, string $previousTier, string $newTier): self
    {
        return new self(
            sourceType: User::class,
            sourceId: $user->id,
            titleKey: 'notifications.loyalty_tier_changed_title',
            messageKey: 'notifications.loyalty_tier_changed_message',
            messageParams: [
                'previous_tier' => $previousTier,
                'new_tier' => $newTier,
            ],
            url: Route::has('loyalty') ? route('loyalty') : null
        );
    }
}
