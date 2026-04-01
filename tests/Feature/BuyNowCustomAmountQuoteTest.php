<?php

use App\Enums\ProductAmountMode;
use App\Models\Package;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a server-side quote for buy now custom amount', function (): void {
    PricingRule::query()->create([
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
        'custom_amount_min' => 500,
        'custom_amount_max' => 1_000_000,
        'custom_amount_step' => 500,
        'entry_price' => 0.01,
    ]);

    $response = $this->actingAs($user)->postJson(route('api.pricing.buy-now-custom-amount-quote'), [
        'product_id' => $product->id,
        'requested_amount' => 2500,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.amount_mode', ProductAmountMode::Custom->value)
        ->assertJsonPath('data.requested_amount', 2500)
        ->assertJsonPath('data.final_total', 25)
        ->assertJsonPath('data.unit_price', 0.01);
});

it('returns validation errors from the shared pricing validator', function (): void {
    PricingRule::query()->create([
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
        'custom_amount_min' => 500,
        'custom_amount_max' => 1_000_000,
        'custom_amount_step' => 500,
        'entry_price' => 0.01,
    ]);

    $response = $this->actingAs($user)->postJson(route('api.pricing.buy-now-custom-amount-quote'), [
        'product_id' => $product->id,
        'requested_amount' => 499,
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', __('messages.min_value', ['field' => __('messages.amount'), 'min' => 500]));
});
