<?php

use App\Actions\Cart\RepriceCustomAmountLinePrice;
use App\Enums\ProductAmountMode;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is silent when product uses fixed amount mode', function () {
    $product = Product::factory()->create([
        'is_active' => true,
        'amount_mode' => ProductAmountMode::Fixed,
        'entry_price' => 10,
    ]);

    $result = app(RepriceCustomAmountLinePrice::class)->handle($product->id, 1, null, 'guest-1');

    expect($result['silent'] ?? false)->toBeTrue();
});

it('returns an error when amount is below minimum', function () {
    $product = Product::factory()->create([
        'is_active' => true,
        'amount_mode' => ProductAmountMode::Custom,
        'entry_price' => 2.5,
        'custom_amount_min' => 10,
        'custom_amount_max' => 1000,
        'custom_amount_step' => 1,
    ]);

    $result = app(RepriceCustomAmountLinePrice::class)->handle($product->id, 5, null, 'guest-2');

    expect($result['ok'] ?? null)->toBeFalse();
    expect($result['silent'] ?? false)->toBeFalse();
});

it('returns price and meta for a valid custom amount', function () {
    $product = Product::factory()->create([
        'is_active' => true,
        'amount_mode' => ProductAmountMode::Custom,
        'entry_price' => 2,
        'custom_amount_min' => 1,
        'custom_amount_max' => 10000,
        'custom_amount_step' => 1,
    ]);

    $result = app(RepriceCustomAmountLinePrice::class)->handle($product->id, 50, null, 'guest-3');

    expect($result['ok'] ?? null)->toBeTrue();
    expect($result['price'])->toBeFloat();
    expect($result['requested_amount'])->toBe(50);
    expect($result['meta']['requested_amount'] ?? null)->toBe(50);
});
