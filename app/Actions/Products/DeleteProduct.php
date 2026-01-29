<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\User;

class DeleteProduct
{
    public function handle(int $productId, int $adminId): void
    {
        $product = Product::query()->findOrFail($productId);
        $product->delete();

        activity()
            ->inLog('admin')
            ->event('product.deleted')
            ->performedOn($product)
            ->causedBy(User::query()->find($adminId))
            ->withProperties([
                'product_id' => $product->id,
                'name' => $product->name,
                'package_id' => $product->package_id,
            ])
            ->log('Product deleted');
    }
}
