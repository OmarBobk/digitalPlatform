<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Timezone;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        // Detect timezone from JavaScript-detected timezone or fallback to country code
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

        activity()
            ->inLog('admin')
            ->event('user.registered')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'user_id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
                'country_code' => $user->country_code,
                'timezone' => $user->timezone,
            ])
            ->log('User registered');

        return $user;
    }
}
