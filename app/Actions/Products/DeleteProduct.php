<?php

namespace App\Actions\Products;

use App\Models\Product;

class DeleteProduct
{
    public function handle(int $productId): void
    {
        Product::query()->findOrFail($productId)->delete();
    }
}
