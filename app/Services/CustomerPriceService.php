<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoyaltyTierConfig;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProductPrice;

/**
 * Single source of customer-facing price including loyalty discount.
 * Uses PriceCalculator for base retail/wholesale; salesperson role gets wholesale, others get retail.
 * Applies tier discount on top. Do not modify PriceCalculator or Product accessors.
 *
 * User-specific overrides (user_product_prices) short-circuit before rules and loyalty.
 */
class CustomerPriceService
{
    private ?int $overridesMemoUserId = null;

    /** @var array<int, float> */
    private array $overridesMemo = [];

    public function __construct(
        private readonly PriceCalculator $priceCalculator
    ) {}

    /**
     * Map of product_id => override price for the given user (single query).
     *
     * @return array<int, float>
     */
    public function getUserOverridesFor(User $user): array
    {
        if ($this->overridesMemoUserId === $user->id) {
            return $this->overridesMemo;
        }

        $this->overridesMemoUserId = $user->id;
        $this->overridesMemo = UserProductPrice::query()
            ->where('user_id', $user->id)
            ->pluck('price', 'product_id')
            ->mapWithKeys(fn ($price, $id): array => [(int) $id => (float) $price])
            ->all();

        return $this->overridesMemo;
    }

    /**
     * Resolve customer price for a product or raw entry price.
     *
     * @return array{base_price: float, discount_amount: float, final_price: float, tier_name: string|null, meta: array{is_override: bool, is_below_cost?: bool}}
     */
    public function priceFor(Product|float $productOrEntryPrice, ?User $user = null, ?array $overridesByProductId = null): array
    {
        $overrides = $this->resolveOverrides($user, $overridesByProductId);

        if ($user !== null && $productOrEntryPrice instanceof Product) {
            $productId = $productOrEntryPrice->getKey();
            if ($productId !== null && isset($overrides[(int) $productId])) {
                $overridePrice = $this->round((float) $overrides[(int) $productId]);
                $entryPrice = $productOrEntryPrice->entry_price !== null
                    ? (float) $productOrEntryPrice->entry_price
                    : null;
                $isBelowCost = $entryPrice !== null && $overridePrice < $entryPrice;

                return [
                    'base_price' => $overridePrice,
                    'discount_amount' => 0.0,
                    'final_price' => $overridePrice,
                    'tier_name' => null,
                    'meta' => [
                        'is_override' => true,
                        'is_below_cost' => $isBelowCost,
                    ],
                ];
            }
        }

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
            'meta' => [
                'is_override' => false,
            ],
        ];
    }

    /**
     * Final price only (for order creation and simple display).
     */
    public function finalPrice(Product|float $productOrEntryPrice, ?User $user = null, ?array $overridesByProductId = null): float
    {
        return $this->priceFor($productOrEntryPrice, $user, $overridesByProductId)['final_price'];
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

    /**
     * @param  array<int, float>|null  $overridesByProductId
     * @return array<int, float>
     */
    private function resolveOverrides(?User $user, ?array $overridesByProductId): array
    {
        if ($user === null) {
            return [];
        }

        if ($overridesByProductId !== null) {
            return $overridesByProductId;
        }

        return $this->getUserOverridesFor($user);
    }
}
