<?php

namespace App\Actions\Products;

use App\Models\Product;

class UpsertProduct
{
    /**
     * @param  array{
     *     package_id:int,
     *     serial:?string,
     *     name:string,
     *     entry_price:float|string|null,
     *     is_active:bool,
     *     order:int
     * }  $data
     */
    public function handle(?int $productId, array $data): Product
    {
        $product = $productId !== null
            ? Product::query()->findOrFail($productId)
            : new Product;

        $serial = $data['serial'] !== null && trim($data['serial']) !== '' ? trim($data['serial']) : null;

        $product->fill([
            'package_id' => $data['package_id'],
            'serial' => $serial,
            'name' => trim($data['name']),
            'entry_price' => $data['entry_price'] ?? null,
            'retail_price' => 0,
            'wholesale_price' => 0,
            'is_active' => $data['is_active'],
            'order' => $data['order'],
        ]);

        $product->save();

        return $product;
    }
}
