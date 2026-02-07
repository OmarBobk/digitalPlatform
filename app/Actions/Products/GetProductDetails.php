<?php

namespace App\Actions\Products;

use App\Models\Product;

class GetProductDetails
{
    public function handle(?int $productId): ?Product
    {
        if ($productId === null) {
            return null;
        }

        return Product::query()
            ->select([
                'id',
                'package_id',
                'serial',
                'name',
                'slug',
                'entry_price',
                'retail_price',
                'wholesale_price',
                'is_active',
                'order',
            ])
            ->with('package:id,name,slug')
            ->find($productId);
    }
}
