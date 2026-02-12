<?php

declare(strict_types=1);

namespace App\Actions\Topups;

use App\Enums\TopupRequestStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class CreateTopupRequestAction
{
    /**
     * Create a top-up request and its pending wallet transaction atomically.
     *
     * @param  array<string, mixed>  $attributes  TopupRequest attributes (user_id, wallet_id?, method, amount, currency, status?, note?)
     */
    public function handle(array $attributes): TopupRequest
    {
        return DB::transaction(function () use ($attributes): TopupRequest {
            if (empty($attributes['wallet_id']) && ! empty($attributes['user_id'])) {
                $user = $attributes['user'] ?? User::query()->find($attributes['user_id']);
                if ($user instanceof User) {
                    $attributes['wallet_id'] = Wallet::forUser($user)->id;
                }
            }
            unset($attributes['user']);

            if (empty($attributes['status'])) {
                $attributes['status'] = TopupRequestStatus::Pending;
            }

            $topupRequest = TopupRequest::withoutEvents(function () use ($attributes): TopupRequest {
                return TopupRequest::create($attributes);
            });

            WalletTransaction::create([
                'wallet_id' => $topupRequest->wallet_id,
                'type' => WalletTransactionType::Topup,
                'direction' => WalletTransactionDirection::Credit,
                'amount' => $topupRequest->amount,
                'status' => WalletTransaction::STATUS_PENDING,
                'reference_type' => TopupRequest::class,
                'reference_id' => $topupRequest->id,
                'meta' => array_filter([
                    'method' => $topupRequest->method->value ?? null,
                    'note' => $topupRequest->note ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
            ]);

            return $topupRequest;
        });
    }
}
