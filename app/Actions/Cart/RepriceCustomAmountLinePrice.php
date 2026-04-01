<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Domain\Pricing\PricingEngine;
use App\Enums\ProductAmountMode;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

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

        $pricingEngine = app(PricingEngine::class);

        try {
            $quote = $pricingEngine->quote($product, 1, (int) $requestedAmount, $user);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? __('messages.invalid_value', ['field' => __('messages.amount')]);

            return ['ok' => false, 'message' => $message];
        } catch (\InvalidArgumentException) {
            return ['ok' => false, 'message' => __('messages.invalid_value', ['field' => __('messages.amount')])];
        }

        $meta = [
            'mode' => ProductAmountMode::Custom->value,
            'requested_amount' => $quote->requestedAmount,
            'entry_price' => $product->entry_price !== null ? (float) $product->entry_price : null,
            'computed_entry_total' => $product->entry_price !== null && $quote->requestedAmount !== null
                ? (float) bcmul(
                    (string) $quote->requestedAmount,
                    number_format((float) $product->entry_price, 6, '.', ''),
                    6
                )
                : null,
        ];

        return [
            'ok' => true,
            'price' => $quote->finalTotal,
            'requested_amount' => (int) $quote->requestedAmount,
            'meta' => $meta,
        ];
    }
}
