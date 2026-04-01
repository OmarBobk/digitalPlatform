<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

final class CustomAmountValidator
{
    private const DEFAULT_CUSTOM_AMOUNT_HARD_CAP = 100000;

    /**
     * @throws ValidationException
     */
    public function validate(Product $product, mixed $rawAmount, string $errorKey = 'requested_amount'): int
    {
        $requestedAmount = filter_var($rawAmount, FILTER_VALIDATE_INT);
        $minimum = $product->custom_amount_min;
        $maximum = $product->custom_amount_max;
        $step = max(1, (int) ($product->custom_amount_step ?? 1));
        $hardCap = (int) config('billing.custom_amount_hard_cap', self::DEFAULT_CUSTOM_AMOUNT_HARD_CAP);

        if ($requestedAmount === false || $requestedAmount <= 0) {
            throw ValidationException::withMessages([
                $errorKey => __('messages.required_field', ['field' => __('messages.amount')]),
            ]);
        }

        if ($hardCap > 0 && $requestedAmount > $hardCap) {
            throw ValidationException::withMessages([
                $errorKey => __('messages.max_value', ['field' => __('messages.amount'), 'max' => $hardCap]),
            ]);
        }

        if ($minimum !== null && $requestedAmount < $minimum) {
            throw ValidationException::withMessages([
                $errorKey => __('messages.min_value', ['field' => __('messages.amount'), 'min' => $minimum]),
            ]);
        }

        if ($maximum !== null && $requestedAmount > $maximum) {
            throw ValidationException::withMessages([
                $errorKey => __('messages.max_value', ['field' => __('messages.amount'), 'max' => $maximum]),
            ]);
        }

        if ($step > 1 && $requestedAmount % $step !== 0) {
            throw ValidationException::withMessages([
                $errorKey => __('messages.invalid_value', ['field' => __('messages.amount')]),
            ]);
        }

        $entryPrice = $product->entry_price !== null ? (float) $product->entry_price : null;
        if ($entryPrice === null || $entryPrice <= 0) {
            throw ValidationException::withMessages([
                $errorKey => __('messages.invalid_value', ['field' => __('messages.entry_price')]),
            ]);
        }

        return (int) $requestedAmount;
    }
}
