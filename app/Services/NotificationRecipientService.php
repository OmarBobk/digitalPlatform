<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Resolves notification recipients. Admins = users with role 'admin' only.
 */
class NotificationRecipientService
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function adminUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::role('admin')->get();
    }
}
