<?php

namespace App\Policies;

use App\Models\Fulfillment;
use App\Models\User;

class FulfillmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_fulfillments');
    }

    public function view(User $user, Fulfillment $fulfillment): bool
    {
        return $user->can('view_fulfillments');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_fulfillments');
    }

    public function update(User $user, Fulfillment $fulfillment): bool
    {
        return $user->can('manage_fulfillments');
    }

    public function delete(User $user, Fulfillment $fulfillment): bool
    {
        return $user->can('manage_fulfillments');
    }

    public function restore(User $user, Fulfillment $fulfillment): bool
    {
        return $user->can('manage_fulfillments');
    }

    public function forceDelete(User $user, Fulfillment $fulfillment): bool
    {
        return $user->can('manage_fulfillments');
    }
}
