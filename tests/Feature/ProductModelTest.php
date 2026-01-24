<?php

use App\Models\Package;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('products table has expected columns', function () {
    expect(Schema::hasColumns('products', [
        'id',
        'package_id',
        'serial',
        'name',
        'slug',
        'retail_price',
        'wholesale_price',
        'is_active',
        'order',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('product slug defaults from name', function () {
    $package = Package::factory()->create();

    $product = Product::create([
        'package_id' => $package->id,
        'name' => 'Starter Product',
        'retail_price' => 10.50,
        'wholesale_price' => 7.25,
        'is_active' => true,
        'order' => 1,
    ]);

    expect($product->slug)->toBe(Str::slug($product->name));
});

test('product belongs to package', function () {
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id]);

    expect($product->package)->not->toBeNull();
    expect($product->package->is($package))->toBeTrue();
});

test('package has many products', function () {
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id]);

    expect($package->products->pluck('id'))->toContain($product->id);
});
