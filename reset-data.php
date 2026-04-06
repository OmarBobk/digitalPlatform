<?php

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Settlement;
use App\Models\TopupRequest;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

// --- Fill these ---
$fulfillmentOwnerUserIds = [24, 25, 26, 1, 22];
$settlementIdsToDelete = [24, 25, 26, 1, 22];
$topupOwnerUserIds = [24, 25, 26, 1, 22];

DB::transaction(function () use ($fulfillmentOwnerUserIds, $settlementIdsToDelete, $topupOwnerUserIds): void {
    // --- 1) Fulfillments for users (via their orders) ---
    $orderIds = Order::query()
        ->whereIn('user_id', $fulfillmentOwnerUserIds)
        ->pluck('id');

    $fulfillmentIds = Fulfillment::query()
        ->whereIn('order_id', $orderIds)
        ->pluck('id');

    // Optional: avoid orphan morph rows on wallet_transactions (Fulfillment reference)
    if ($fulfillmentIds->isNotEmpty()) {
        WalletTransaction::query()
            ->where('reference_type', Fulfillment::class)
            ->whereIn('reference_id', $fulfillmentIds)
            ->delete();
    }

    Fulfillment::query()->whereIn('order_id', $orderIds)->delete();

    // --- 2) Settlements by id (pivot rows cascade; does NOT fix platform wallet / settlement wallet_tx) ---
    if ($settlementIdsToDelete !== []) {
        // Optional: remove ledger rows pointing at these settlements (balance still wrong until you fix manually)
        WalletTransaction::query()
            ->where('reference_type', Settlement::class)
            ->whereIn('reference_id', $settlementIdsToDelete)
            ->delete();

        Settlement::query()->whereIn('id', $settlementIdsToDelete)->delete();
    }

    // --- 3) Topups for users (TopupRequest = your "topups") ---
    $topupIds = TopupRequest::query()
        ->whereIn('user_id', $topupOwnerUserIds)
        ->pluck('id');

    if ($topupIds->isNotEmpty()) {
        WalletTransaction::query()
            ->where('reference_type', TopupRequest::class)
            ->whereIn('reference_id', $topupIds)
            ->delete();
    }

    TopupRequest::query()->whereIn('user_id', $topupOwnerUserIds)->delete();
});
