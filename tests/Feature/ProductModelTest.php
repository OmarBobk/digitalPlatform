<?php

use App\Enums\ProductAmountMode;
use App\Models\Package;
use App\Models\PricingRule;
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
        'entry_price',
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
        'entry_price' => 10.50,
        'is_active' => true,
        'order' => 1,
    ]);

    expect($product->slug)->toBe(Str::slug($product->name));
});

test('product persists sub cent per unit entry price without rounding to zero', function (): void {
    PricingRule::create([
        'min_price' => 0,
        'max_price' => 999999.99,
        'wholesale_percentage' => 0,
        'retail_percentage' => 0,
        'priority' => 0,
        'is_active' => true,
    ]);

    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 0.00178316,
        'amount_mode' => ProductAmountMode::Custom,
        'amount_unit_label' => 'Crystal',
        'custom_amount_min' => 500,
        'custom_amount_max' => 1_000_000,
        'custom_amount_step' => 500,
    ]);

    $product->refresh();

    expect((float) $product->getRawOriginal('entry_price'))->toBeGreaterThan(0);
    expect((float) $product->entry_price)->toBeGreaterThan(0);
});

test('product retail and wholesale prices are derived from entry price', function () {
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id, 'entry_price' => 100]);

    expect($product->retail_price)->toBe(100.0);
    expect($product->wholesale_price)->toBe(100.0);
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
