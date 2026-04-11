<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Models\Product;

class UpdateProductEntryPrice
{
    public function handle(Product $product, string|float|int $entryPrice): Product
    {
        $product->entry_price = is_string($entryPrice) ? $entryPrice : (string) $entryPrice;
        $product->save();

        return $product->fresh();
    }
}
