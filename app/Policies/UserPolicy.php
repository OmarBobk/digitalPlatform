<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    private function canManageUsers(User $user): bool
    {
        return $user->can('manage_users');
    }

    public function viewAny(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->canManageUsers($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->canManageUsers($user);
    }

    public function delete(User $user, User $model): bool
    {
        if (! $this->canManageUsers($user)) {
            return false;
        }
        if ($user->id === $model->id) {
            return false;
        }

        return true;
    }

    public function restore(User $user, User $model): bool
    {
        return $this->canManageUsers($user);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    public function block(User $user, User $model): bool
    {
        if (! $this->canManageUsers($user)) {
            return false;
        }
        if ($user->id === $model->id) {
            return false;
        }

        return true;
    }

    public function unblock(User $user, User $model): bool
    {
        return $this->canManageUsers($user);
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $this->canManageUsers($user);
    }

    public function verifyEmail(User $user, User $model): bool
    {
        return $this->canManageUsers($user);
    }

    public function assignRoles(User $user, User $model): bool
    {
        return $this->canManageUsers($user);
    }

    public function assignPermissions(User $user, User $model): bool
    {
        return $this->canManageUsers($user);
    }
}
