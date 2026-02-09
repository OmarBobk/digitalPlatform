<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WalletTransaction;

/**
 * Authoritative rolling spend for loyalty tier evaluation.
 * Uses fulfillments as the unit of realization; excludes any fulfillment with a posted refund.
 * Same refund-detection logic as settlement eligibility.
 */
class LoyaltySpendService
{
    /**
     * Compute rolling spend for a user (sum of order_item.unit_price per eligible fulfillment).
     * Does not store any aggregate.
     */
    public function computeRollingSpend(User $user, int $windowDays = 90): float
    {
        $since = now()->subDays($windowDays);

        $fulfillments = Fulfillment::query()
            ->where('status', FulfillmentStatus::Completed)
            ->where('completed_at', '>=', $since)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
            ->with('orderItem')
            ->get();

        $eligible = $fulfillments->filter(fn (Fulfillment $f) => ! $this->hasPostedRefund($f));

        return (float) $eligible->sum(fn (Fulfillment $f) => (float) $f->orderItem->unit_price);
    }

    /**
     * Same logic as ProfitSettleCommand::hasPostedRefund (settlement eligibility).
     */
    private function hasPostedRefund(Fulfillment $fulfillment): bool
    {
        $directRefund = WalletTransaction::query()
            ->where('type', WalletTransactionType::Refund)
            ->where('status', WalletTransaction::STATUS_POSTED)
            ->where('reference_type', Fulfillment::class)
            ->where('reference_id', $fulfillment->id)
            ->exists();

        if ($directRefund) {
            return true;
        }

        $orderRefund = WalletTransaction::query()
            ->where('type', WalletTransactionType::Refund)
            ->where('status', WalletTransaction::STATUS_POSTED)
            ->where('reference_type', Order::class)
            ->where('reference_id', $fulfillment->order_id)
            ->exists();

        if ($orderRefund) {
            return true;
        }

        $itemRefunds = WalletTransaction::query()
            ->where('type', WalletTransactionType::Refund)
            ->where('status', WalletTransaction::STATUS_POSTED)
            ->where('reference_type', OrderItem::class)
            ->where('reference_id', $fulfillment->order_item_id)
            ->get();

        foreach ($itemRefunds as $tx) {
            $metaFulfillmentId = (int) (is_array($tx->meta) ? ($tx->meta['fulfillment_id'] ?? 0) : 0);
            if ($metaFulfillmentId === $fulfillment->id) {
                return true;
            }
        }

        return false;
    }
}
