<?php

use App\Enums\ProductAmountMode;
use App\Models\Package;
use App\Models\PricingRule;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('reprices custom amount line when quick-add listener runs', function (): void {
    PricingRule::create([
        'min_price' => 0,
        'max_price' => 999999.99,
        'wholesale_percentage' => 0,
        'retail_percentage' => 0,
        'priority' => 0,
        'is_active' => true,
    ]);

    $package = Package::factory()->create(['is_active' => true]);
    $product = Product::factory()->for($package)->create([
        'is_active' => true,
        'amount_mode' => ProductAmountMode::Custom,
        'custom_amount_min' => 500,
        'custom_amount_max' => 1_000_000,
        'custom_amount_step' => 1,
        'entry_price' => 0.01,
    ]);

    Livewire::test('cart.dropdown')
        ->call('repriceAfterQuickAdd', $product->id, 500)
        ->assertDispatched('cart-custom-amount-priced');
});
