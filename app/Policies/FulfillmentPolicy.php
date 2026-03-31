<?php

namespace App\Policies;

use App\Models\Fulfillment;
use App\Models\User;

class FulfillmentPolicy
{
    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view_fulfillments');
    }

    public function view(User $user, Fulfillment $fulfillment): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $user->can('view_fulfillments')) {
            return false;
        }

        return $fulfillment->claimed_by === $user->id
            || ($fulfillment->claimed_by === null && $fulfillment->status->value === 'queued');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_fulfillments');
    }

    public function update(User $user, Fulfillment $fulfillment): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $user->can('manage_fulfillments')) {
            return false;
        }

        return $fulfillment->claimed_by === $user->id;
    }

    public function claim(User $user, Fulfillment $fulfillment): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $user->can('manage_fulfillments')) {
            return false;
        }

        return $fulfillment->claimed_by === null
            && $fulfillment->status->value === 'queued';
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
