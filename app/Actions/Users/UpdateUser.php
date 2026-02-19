<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\Timezone;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    use ProfileValidationRules;

    /**
     * @param  array<string, mixed>  $input  Must include name, username, email; optional: phone, country_code, roles, permissions
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input, int $causedById): void
    {
        Validator::make($input, $this->profileRules($user->id))->validate();

        $timezone = Timezone::detect(
            $input['timezone_detected'] ?? null,
            $input['country_code'] ?? null
        );

        $user->forceFill([
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'country_code' => $input['country_code'] ?? null,
            'timezone' => $timezone,
            'profile_photo' => $input['profile_photo'] ?? $user->profile_photo,
        ])->save();

        $loggedRoles = false;
        $loggedPermissions = false;

        $roleNames = $input['roles'] ?? null;
        if (is_array($roleNames)) {
            $previousRoles = $user->getRoleNames()->all();
            $user->syncRoles($roleNames);
            if ($previousRoles !== $roleNames) {
                $this->logRolesUpdated($user, $roleNames, $previousRoles, $causedById);
                $loggedRoles = true;
            }
        }

        $permissionNames = $input['permissions'] ?? null;
        if (is_array($permissionNames)) {
            $previousPermissions = $user->getDirectPermissions()->pluck('name')->all();
            $user->syncPermissions($permissionNames);
            if ($previousPermissions !== $permissionNames) {
                $this->logPermissionsUpdated($user, $permissionNames, $previousPermissions, $causedById);
                $loggedPermissions = true;
            }
        }

        if (! $loggedRoles && ! $loggedPermissions) {
            $causer = User::query()->find($causedById);
            activity()
                ->inLog('admin')
                ->event('user.updated')
                ->performedOn($user)
                ->causedBy($causer)
                ->withProperties([
                    'user_id' => $user->id,
                    'email' => $user->email,
                ])
                ->log('User updated by admin');
        }
    }

    /**
     * @param  array<int, string>  $newRoles
     * @param  array<int, string>  $oldRoles
     */
    private function logRolesUpdated(User $user, array $newRoles, array $oldRoles, int $causedById): void
    {
        $causer = User::query()->find($causedById);
        $adminUsername = $causer?->username ?? $causer?->name ?? 'admin';
        $targetUsername = $user->username ?? $user->name ?? (string) $user->id;

        $removed = array_values(array_diff($oldRoles, $newRoles));
        $added = array_values(array_diff($newRoles, $oldRoles));

        $parts = [];
        if ($removed !== []) {
            $rolesList = implode(', ', $removed);
            $parts[] = "removed role {$rolesList} from {$targetUsername}";
        }
        if ($added !== []) {
            $rolesList = implode(', ', $added);
            $parts[] = "assigned role {$rolesList} to {$targetUsername}";
        }
        $action = implode(' and ', $parts) ?: "updated roles for {$targetUsername}";
        $description = "Admin({$adminUsername}) {$action}";

        activity()
            ->inLog('admin')
            ->event('user.roles_updated')
            ->performedOn($user)
            ->causedBy($causer)
            ->withProperties([
                'user_id' => $user->id,
                'roles' => $newRoles,
                'previous_roles' => $oldRoles,
            ])
            ->log($description);
    }

    /**
     * @param  array<int, string>  $newPermissions
     * @param  array<int, string>  $oldPermissions
     */
    private function logPermissionsUpdated(User $user, array $newPermissions, array $oldPermissions, int $causedById): void
    {
        $causer = User::query()->find($causedById);
        $adminUsername = $causer?->username ?? $causer?->name ?? 'admin';
        $targetUsername = $user->username ?? $user->name ?? (string) $user->id;

        $removed = array_values(array_diff($oldPermissions, $newPermissions));
        $added = array_values(array_diff($newPermissions, $oldPermissions));

        $parts = [];
        if ($removed !== []) {
            $permList = implode(', ', $removed);
            $parts[] = "removed permission {$permList} from {$targetUsername}";
        }
        if ($added !== []) {
            $permList = implode(', ', $added);
            $parts[] = "assigned permission {$permList} to {$targetUsername}";
        }
        $action = implode(' and ', $parts) ?: "updated permissions for {$targetUsername}";
        $description = "Admin({$adminUsername}) {$action}";

        activity()
            ->inLog('admin')
            ->event('user.permissions_updated')
            ->performedOn($user)
            ->causedBy($causer)
            ->withProperties([
                'user_id' => $user->id,
                'permissions' => $newPermissions,
                'previous_permissions' => $oldPermissions,
            ])
            ->log($description);
    }
}
