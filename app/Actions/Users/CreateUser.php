<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Timezone;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateUser
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @param  array<string, mixed>  $input  name, username, email, password; optional: phone, country_code, roles, permissions
     *
     * @throws ValidationException
     */
    public function handle(array $input, int $causedById): User
    {
        Validator::make([
            ...$input,
            'preferred_currency' => $this->normalizePreferredCurrency($input['preferred_currency'] ?? null),
        ], [
            ...$this->profileRules(null),
            'password' => $this->passwordRules(),
        ])->validate();

        $timezone = Timezone::detect(
            $input['timezone_detected'] ?? null,
            $input['country_code'] ?? null
        );

        $user = User::create([
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'password' => $input['password'],
            'phone' => $input['phone'] ?? null,
            'country_code' => $input['country_code'] ?? null,
            'timezone' => $timezone,
            'profile_photo' => $input['profile_photo'] ?? null,
            'is_active' => true,
        ]);

        $roleNames = $input['roles'] ?? [];
        if (is_array($roleNames) && $roleNames !== []) {
            $user->syncRoles($roleNames);
        }

        $permissionNames = $input['permissions'] ?? [];
        if (is_array($permissionNames) && $permissionNames !== []) {
            $user->syncPermissions($permissionNames);
        }

        $causer = User::query()->find($causedById);
        activity()
            ->inLog('admin')
            ->event('user.created')
            ->performedOn($user)
            ->causedBy($causer)
            ->withProperties([
                'user_id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
            ])
            ->log('User created by admin');

        return $user;
    }

    private function normalizePreferredCurrency(mixed $value): string
    {
        return in_array($value, ['USD', 'TRY'], true) ? $value : 'USD';
    }
}
