<?php

declare(strict_types=1);

use App\Domain\Pricing\PricingEngine;
use App\Enums\ProductAmountMode;
use App\Models\Package;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProductPrice;
use App\Support\BuyNowClientPricingContext;

it('preview matches pricing engine for tiered custom amounts', function (): void {
    PricingRule::query()->delete();
    PricingRule::create([
        'min_price' => 0,
        'max_price' => 100,
        'wholesale_percentage' => 0,
        'retail_percentage' => 10,
        'priority' => 0,
        'is_active' => true,
    ]);
    PricingRule::create([
        'min_price' => 100,
        'max_price' => 1000,
        'wholesale_percentage' => 0,
        'retail_percentage' => 5,
        'priority' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    $package = Package::factory()->create(['is_active' => true]);
    $product = Product::factory()->for($package)->create([
        'is_active' => true,
        'amount_mode' => ProductAmountMode::Custom,
        'custom_amount_min' => 1000,
        'custom_amount_max' => 1_000_000,
        'custom_amount_step' => 1000,
        'entry_price' => 0.01,
    ]);

    $context = BuyNowClientPricingContext::build($user, $product);
    expect($context['client_pricable'])->toBeTrue();

    $engine = app(PricingEngine::class);

    foreach ([1000, 20_000, 50_000] as $amount) {
        $quote = $engine->quote($product, 1, $amount, $user);
        $preview = BuyNowClientPricingContext::previewFinalPrice($amount, $context);
        expect($preview)->not->toBeNull()
            ->and(abs((float) $preview - (float) $quote->finalTotal))->toBeLessThan(0.0001);
    }
});

it('is not client pricable when user has product price override', function (): void {
    PricingRule::create([
        'min_price' => 0,
        'max_price' => 999999.99,
        'wholesale_percentage' => 0,
        'retail_percentage' => 0,
        'priority' => 0,
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    $package = Package::factory()->create(['is_active' => true]);
    $product = Product::factory()->for($package)->create([
        'is_active' => true,
        'amount_mode' => ProductAmountMode::Custom,
        'entry_price' => 0.01,
    ]);

    UserProductPrice::query()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'price' => 1.5,
    ]);

    $context = BuyNowClientPricingContext::build($user, $product);
    expect($context['client_pricable'])->toBeFalse()
        ->and(BuyNowClientPricingContext::previewFinalPrice(1000, $context))->toBeNull();
});
