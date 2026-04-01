<?php

declare(strict_types=1);

namespace App\Actions\Pricing;

use App\Domain\Pricing\PricingEngine;
use App\Models\Product;
use App\Models\User;

class QuoteBuyNowCustomAmountLine
{
    public function __construct(
        private readonly PricingEngine $pricingEngine
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(int $productId, int $requestedAmount, ?User $user = null): ?array
    {
        $product = Product::query()
            ->select([
                'id',
                'entry_price',
                'amount_mode',
                'custom_amount_min',
                'custom_amount_max',
                'custom_amount_step',
            ])
            ->whereKey($productId)
            ->where('is_active', true)
            ->first();

        if ($product === null) {
            return null;
        }

        return $this->pricingEngine->quote($product, 1, $requestedAmount, $user)->toArray();
    }
}
