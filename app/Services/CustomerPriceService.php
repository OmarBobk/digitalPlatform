<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoyaltyTierConfig;
use App\Models\Product;
use App\Models\User;

/**
 * Single source of customer-facing price including loyalty discount.
 * Uses PriceCalculator for base retail/wholesale; salesperson role gets wholesale, others get retail.
 * Applies tier discount on top. Do not modify PriceCalculator or Product accessors.
 */
class CustomerPriceService
{
    public function __construct(
        private readonly PriceCalculator $priceCalculator
    ) {}

    /**
     * Resolve customer price for a product or raw entry price.
     *
     * @return array{base_price: float, discount_amount: float, final_price: float, tier_name: string|null}
     */
    public function priceFor(Product|float $productOrEntryPrice, ?User $user = null): array
    {
        $basePrice = $this->resolveBasePrice($productOrEntryPrice, $user);
        $tierConfig = $user !== null ? $this->tierConfigForUser($user) : null;
        $discountPercent = $tierConfig !== null ? (float) $tierConfig->discount_percentage : 0.0;
        $discountAmount = $this->round($basePrice * $discountPercent / 100);
        $finalPrice = $this->round($basePrice - $discountAmount);
        $tierName = $tierConfig?->name;

        return [
            'base_price' => $basePrice,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'tier_name' => $tierName,
        ];
    }

    /**
     * Final price only (for order creation and simple display).
     */
    public function finalPrice(Product|float $productOrEntryPrice, ?User $user = null): float
    {
        return $this->priceFor($productOrEntryPrice, $user)['final_price'];
    }

    /**
     * Base price before loyalty discount: wholesale for salesperson role, retail otherwise.
     */
    private function resolveBasePrice(Product|float $productOrEntryPrice, ?User $user = null): float
    {
        $useWholesale = $user !== null && $user->hasRole('salesperson');
        $prices = $this->resolveRetailAndWholesale($productOrEntryPrice);

        return $useWholesale ? $prices['wholesale_price'] : $prices['retail_price'];
    }

    /**
     * @return array{retail_price: float, wholesale_price: float}
     */
    private function resolveRetailAndWholesale(Product|float $productOrEntryPrice): array
    {
        if ($productOrEntryPrice instanceof Product) {
            $entryPrice = $productOrEntryPrice->entry_price !== null
                ? (float) $productOrEntryPrice->entry_price
                : null;
            if ($entryPrice !== null) {
                return $this->priceCalculator->calculate($entryPrice);
            }

            return [
                'retail_price' => (float) $productOrEntryPrice->retail_price,
                'wholesale_price' => (float) $productOrEntryPrice->wholesale_price,
            ];
        }

        return $this->priceCalculator->calculate((float) $productOrEntryPrice);
    }

    private function tierConfigForUser(User $user): ?LoyaltyTierConfig
    {
        $role = $user->loyaltyRole();
        if ($role === null) {
            return null;
        }
        $tierName = $user->loyalty_tier?->value ?? 'bronze';

        return LoyaltyTierConfig::query()->forRole($role)->where('name', $tierName)->first();
    }

    private function round(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_EVEN);
    }
}
