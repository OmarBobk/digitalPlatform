<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Pricing\QuoteBuyNowCustomAmountLine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BuyNowCustomAmountQuoteController extends Controller
{
    public function __invoke(Request $request, QuoteBuyNowCustomAmountLine $quoteAction)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'requested_amount' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $quote = $quoteAction->handle(
                (int) $validated['product_id'],
                (int) $validated['requested_amount'],
                $request->user()
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first()
                    ?? __('messages.invalid_value', ['field' => __('messages.amount')]),
                'errors' => $exception->errors(),
            ], 422);
        }

        if ($quote === null) {
            return response()->json([
                'message' => __('messages.product_missing'),
            ], 404);
        }

        return response()->json([
            'data' => $quote,
        ]);
    }
}
