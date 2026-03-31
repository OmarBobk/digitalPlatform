<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoyaltyTierConfig;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProductPrice;
use InvalidArgumentException;

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
     * @return array{base_price: float, discount_amount: float, final_price: float, tier_name: string|null, meta: array{is_override: bool, is_below_cost?: bool, is_floor_applied?: bool}}
     */
    public function priceFor(Product|float $productOrEntryPrice, ?User $user = null, ?array $overridesByProductId = null): array
    {
        $overrides = $this->resolveOverrides($user, $overridesByProductId);

        if ($user !== null && $productOrEntryPrice instanceof Product) {
            $productId = $productOrEntryPrice->getKey();
            if ($productId !== null && isset($overrides[(int) $productId])) {
                $delta = $this->round((float) $overrides[(int) $productId]);
                $basePrice = $this->resolveBasePrice($productOrEntryPrice, $user);
                $adjustedBasePrice = $this->round($basePrice + $delta);

                $tierConfig = $this->tierConfigForUser($user);
                $discountPercent = $tierConfig !== null ? (float) $tierConfig->discount_percentage : 0.0;
                $discountAmount = $this->round($adjustedBasePrice * $discountPercent / 100);
                $finalPrice = $this->round($adjustedBasePrice - $discountAmount);
                $tierName = $tierConfig?->name;

                $entryPrice = $productOrEntryPrice->entry_price !== null
                    ? (float) $productOrEntryPrice->entry_price
                    : null;
                $isFloorApplied = false;
                if ($entryPrice !== null && $finalPrice < $entryPrice) {
                    $finalPrice = $this->round($entryPrice);
                    $discountAmount = $this->round($adjustedBasePrice - $finalPrice);
                    $isFloorApplied = true;
                }
                $isBelowCost = $entryPrice !== null && $finalPrice < $entryPrice;

                return [
                    'base_price' => $adjustedBasePrice,
                    'discount_amount' => $discountAmount,
                    'final_price' => $finalPrice,
                    'tier_name' => $tierName,
                    'meta' => [
                        'is_override' => true,
                        'is_below_cost' => $isBelowCost,
                        'is_floor_applied' => $isFloorApplied,
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
        $isFloorApplied = false;

        if ($productOrEntryPrice instanceof Product && $productOrEntryPrice->entry_price !== null && $finalPrice < (float) $productOrEntryPrice->entry_price) {
            $finalPrice = $this->round((float) $productOrEntryPrice->entry_price);
            $discountAmount = $this->round($basePrice - $finalPrice);
            $isFloorApplied = true;
        }

        return [
            'base_price' => $basePrice,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'tier_name' => $tierName,
            'meta' => [
                'is_override' => false,
                'is_floor_applied' => $isFloorApplied,
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
     * Resolve customer price for amount-based products.
     * Uses BCMath for entry total multiplication and preserves normal pricing pipeline.
     *
     * @return array{base_price: float, discount_amount: float, final_price: float, tier_name: string|null, meta: array{is_override: bool, is_below_cost?: bool, is_floor_applied?: bool}}
     */
    public function finalPriceForAmount(Product $product, int $amount, User $user, ?array $overridesByProductId = null): array
    {
        return $this->priceForComputedTotal($product, $amount, $user, $overridesByProductId);
    }

    /**
     * Resolve customer price for fixed-quantity products.
     * Pricing rules apply on total base (entry_price * quantity).
     *
     * @return array{
     *   base_price: float,
     *   discount_amount: float,
     *   final_price: float,
     *   final_total: float,
     *   unit_price: float,
     *   tier_name: string|null,
     *   meta: array{is_override: bool, is_below_cost?: bool, is_floor_applied?: bool}
     * }
     */
    public function finalPriceForQuantity(Product $product, int $quantity, User $user, ?array $overridesByProductId = null): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $prices = $this->priceForComputedTotal($product, $quantity, $user, $overridesByProductId);
        $finalTotal = (float) $prices['final_price'];

        return [
            ...$prices,
            'final_total' => $finalTotal,
            'unit_price' => $this->divideTotalByQuantity($finalTotal, $quantity),
        ];
    }

    /**
     * @return array{base_price: float, discount_amount: float, final_price: float, tier_name: string|null, meta: array{is_override: bool, is_below_cost?: bool, is_floor_applied?: bool}}
     */
    private function priceForComputedTotal(Product $product, int $multiplier, User $user, ?array $overridesByProductId = null): array
    {
        $entryPrice = $product->entry_price !== null
            ? (float) $product->entry_price
            : null;

        if ($entryPrice === null || $entryPrice <= 0) {
            throw new InvalidArgumentException('Invalid entry price for product.');
        }

        $computedEntryTotal = $this->multiplyAmountByEntryPrice($multiplier, $entryPrice);
        $pricingProduct = clone $product;
        $pricingProduct->setAttribute('entry_price', $computedEntryTotal);

        return $this->priceFor($pricingProduct, $user, $overridesByProductId);
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

    private function multiplyAmountByEntryPrice(int $amount, float $entryPrice): float
    {
        $entryAsDecimal = number_format($entryPrice, 6, '.', '');
        $computed = bcmul((string) $amount, $entryAsDecimal, 6);

        return (float) $computed;
    }

    private function divideTotalByQuantity(float $finalTotal, int $quantity): float
    {
        $totalAsDecimal = number_format($finalTotal, 8, '.', '');
        $computed = bcdiv($totalAsDecimal, (string) $quantity, 8);

        return (float) $computed;
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
