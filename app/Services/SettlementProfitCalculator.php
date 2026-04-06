<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductAmountMode;
use App\Models\OrderItem;

/**
 * Realized profit for a single fulfillment from its order item snapshot.
 *
 * Fixed mode: one fulfillment ≈ one quantity unit; margin is per-unit retail minus per-unit entry.
 * Custom mode: one fulfillment covers the full line; margin is line_total minus snapshot entry base (computed_entry_total).
 */
final class SettlementProfitCalculator
{
    public function forOrderItem(OrderItem $item): float
    {
        $unitPrice = (float) $item->unit_price;
        $entryPrice = (float) ($item->entry_price ?? 0);
        $mode = $item->amount_mode ?? ProductAmountMode::Fixed;

        if ($mode === ProductAmountMode::Custom) {
            $lineTotal = (float) ($item->line_total ?? 0);
            $lineEntryTotal = $this->resolveComputedEntryTotal($item);

            if ($lineEntryTotal !== null) {
                return max(0, round($lineTotal - $lineEntryTotal, 2));
            }
        }

        return max(0, round($unitPrice - $entryPrice, 2));
    }

    private function resolveComputedEntryTotal(OrderItem $item): ?float
    {
        $meta = $item->pricing_meta;
        if (is_array($meta) && array_key_exists('computed_entry_total', $meta)) {
            $raw = $meta['computed_entry_total'];
            if (is_numeric($raw)) {
                return (float) $raw;
            }
        }

        $requested = $item->requested_amount;
        if ($requested !== null && $requested > 0 && $item->entry_price !== null && $item->entry_price !== '') {
            return (float) bcmul(
                (string) $requested,
                number_format((float) $item->entry_price, 6, '.', ''),
                6
            );
        }

        return null;
    }
}
