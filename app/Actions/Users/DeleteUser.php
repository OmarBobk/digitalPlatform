<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    public function handle(User $user, int $causedById): void
    {
        $userId = $user->id;
        $email = $user->email;
        $username = $user->username;

        DB::transaction(function () use ($user): void {
            $orderIds = Order::query()->where('user_id', $user->id)->pluck('id');

            Fulfillment::query()->whereIn('order_id', $orderIds)->delete();
            Order::query()->where('user_id', $user->id)->delete();
            TopupRequest::query()->where('user_id', $user->id)->delete();

            $wallet = Wallet::query()->where('user_id', $user->id)->first();
            if ($wallet !== null) {
                WalletTransaction::query()->where('wallet_id', $wallet->id)->delete();
                $wallet->delete();
            }

            $user->delete();
        });

        $causer = User::query()->find($causedById);
        activity()
            ->inLog('admin')
            ->event('user.deleted')
            ->causedBy($causer)
            ->withProperties([
                'user_id' => $userId,
                'email' => $email,
                'username' => $username,
            ])
            ->log('User deleted by admin');
    }
}
