<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminResetUserPassword
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input, int $causedById): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();

        $causer = User::query()->find($causedById);
        activity()
            ->inLog('admin')
            ->event('user.password_reset')
            ->performedOn($user)
            ->causedBy($causer)
            ->withProperties([
                'user_id' => $user->id,
                'email' => $user->email,
            ])
            ->log('User password reset by admin');
    }
}
