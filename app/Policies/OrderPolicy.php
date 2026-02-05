<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_orders');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('view_orders');
    }

    public function create(User $user): bool
    {
        return $user->can('create_orders');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('edit_orders');
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->can('delete_orders');
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->can('delete_orders');
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return $user->can('delete_orders');
    }
}
