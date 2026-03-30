<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PricingRule;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Single source of truth for deriving retail and wholesale prices from entry price.
 * Uses active pricing rules (min_price <= entry_price < max_price, first match by priority).
 * Applies bankers rounding (round half to even) to final prices.
 *
 * When no rule matches, throws InvalidArgumentException.
 * Ensure a default rule (e.g. 0 to 999999.99) exists so every entry price is covered.
 */
class PriceCalculator
{
    /**
     * @return array{retail_price: float, wholesale_price: float}
     */
    public function calculate(float $entryPrice, int $roundingScale = 2): array
    {
        $rule = $this->resolveRule($entryPrice);

        if ($rule === null) {
            throw new InvalidArgumentException(
                "No active pricing rule matches entry price [{$entryPrice}]. ".
                'Ensure a default rule covers all entry price ranges.'
            );
        }

        $roundingScale = max(0, min(8, $roundingScale));

        $retailPrice = $this->applyBankersRounding(
            $entryPrice * (1 + (float) $rule->retail_percentage / 100),
            $roundingScale
        );
        $wholesalePrice = $this->applyBankersRounding(
            $entryPrice * (1 + (float) $rule->wholesale_percentage / 100),
            $roundingScale
        );

        return [
            'retail_price' => $retailPrice,
            'wholesale_price' => $wholesalePrice,
        ];
    }

    /**
     * Bankers rounding: round half to even (PHP_ROUND_HALF_EVEN).
     */
    private function applyBankersRounding(float $value, int $decimals = 2): float
    {
        return round($value, $decimals, PHP_ROUND_HALF_EVEN);
    }

    private function resolveRule(float $entryPrice): ?PricingRule
    {
        $entryKey = number_format($entryPrice, 6, '.', '');
        $cacheKey = 'pricing_rule_'.$entryKey;

        return Cache::remember($cacheKey, 60, function () use ($entryPrice): ?PricingRule {
            return PricingRule::query()
                ->where('is_active', true)
                ->where('min_price', '<=', $entryPrice)
                ->where('max_price', '>', $entryPrice)
                ->orderBy('priority')
                ->first();
        });
    }
}
