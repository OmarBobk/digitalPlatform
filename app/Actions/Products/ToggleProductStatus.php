<?php

namespace App\Actions\Products;

use App\Models\Product;

class ToggleProductStatus
{
    public function handle(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);

        $product->update([
            'is_active' => ! $product->is_active,
        ]);
    }
}
