<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Enums\ProductAmountMode;
use App\Models\Product;
use App\Models\User;
use App\Services\CustomerPriceService;
use Illuminate\Support\Facades\RateLimiter;

final class RepriceCustomAmountLinePrice
{
    /**
     * @return array{ok: true, price: float, requested_amount: int, meta: array<string, mixed>}|array{ok: false, message: string, silent?: bool}
     */
    public function handle(int $productId, mixed $requestedAmount, ?User $user, string $rateLimiterIdentity): array
    {
        $rateLimitKey = sprintf('cart-reprice:%s:%d', $rateLimiterIdentity, $productId);
        $allowed = false;
        RateLimiter::attempt($rateLimitKey, 20, function () use (&$allowed): void {
            $allowed = true;
        }, 60);

        if (! $allowed) {
            return ['ok' => false, 'message' => __('messages.something_went_wrong_checkout')];
        }

        $amount = (int) $requestedAmount;

        if ($amount <= 0) {
            return ['ok' => false, 'message' => __('messages.required_field', ['field' => __('messages.amount')])];
        }

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

        if ($product === null || ($product->amount_mode ?? ProductAmountMode::Fixed) !== ProductAmountMode::Custom) {
            return ['ok' => false, 'silent' => true, 'message' => ''];
        }

        $minimum = $product->custom_amount_min;
        $maximum = $product->custom_amount_max;
        $step = $product->custom_amount_step ?? 1;

        if ($minimum !== null && $amount < $minimum) {
            return ['ok' => false, 'message' => __('messages.min_value', ['field' => __('messages.amount'), 'min' => $minimum])];
        }

        if ($maximum !== null && $amount > $maximum) {
            return ['ok' => false, 'message' => __('messages.max_value', ['field' => __('messages.amount'), 'max' => $maximum])];
        }

        if ($step > 1 && $amount % $step !== 0) {
            return ['ok' => false, 'message' => __('messages.invalid_value', ['field' => __('messages.amount')])];
        }

        $entryPrice = $product->entry_price !== null ? (float) $product->entry_price : null;

        if ($entryPrice === null) {
            return ['ok' => false, 'message' => __('messages.invalid_value', ['field' => __('messages.amount')])];
        }

        $computedEntryTotal = round($entryPrice * $amount, 2);
        $pricingProduct = clone $product;
        $pricingProduct->setAttribute('entry_price', $computedEntryTotal);

        $priceService = app(CustomerPriceService::class);
        $overrides = $user !== null ? $priceService->getUserOverridesFor($user) : [];
        $prices = $priceService->priceFor($pricingProduct, $user, $overrides);

        $meta = [
            'mode' => ProductAmountMode::Custom->value,
            'requested_amount' => $amount,
            'entry_price' => $entryPrice,
            'computed_entry_total' => $computedEntryTotal,
        ];

        return [
            'ok' => true,
            'price' => (float) $prices['final_price'],
            'requested_amount' => $amount,
            'meta' => $meta,
        ];
    }
}
