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
     *     retail_price:float|string,
     *     wholesale_price:float|string,
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
            'retail_price' => $data['retail_price'],
            'wholesale_price' => $data['wholesale_price'],
            'is_active' => $data['is_active'],
            'order' => $data['order'],
        ]);

        $product->save();

        return $product;
    }
}
