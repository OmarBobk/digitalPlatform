<?php

use App\Models\Package;
use App\Models\PackageRequirement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows package products list when opening package overlay', function () {
    $package = Package::factory()->create([
        'is_active' => true,
        'name' => 'Soul Crystal',
    ]);

    Product::factory()->for($package)->create([
        'name' => 'Soul 1000 Crystal',
        'is_active' => true,
        'order' => 1,
    ]);

    Livewire::test('main.buy-now-modal')
        ->call('openPackageOverlay', $package->id)
        ->assertSet('showPackageProducts', true)
        ->assertSet('showBuyNowModal', true)
        ->assertSet('selectedPackageName', 'Soul Crystal')
        ->assertSee('Soul 1000 Crystal')
        ->assertDontSee(__('messages.no_products_yet'));
});

it('shows no products yet when package has no active products', function () {
    $package = Package::factory()->create([
        'is_active' => true,
        'name' => 'Empty Package',
    ]);

    Livewire::test('main.buy-now-modal')
        ->call('openPackageOverlay', $package->id)
        ->assertSet('showPackageProducts', true)
        ->assertSee(__('messages.no_products_yet'));
});

it('shows requirement fields when opening buy now from package overlay', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create([
        'is_active' => true,
        'name' => 'Package With Requirements',
    ]);

    PackageRequirement::factory()->create([
        'package_id' => $package->id,
        'key' => 'player_id',
        'label' => 'Player ID',
        'type' => 'string',
        'is_required' => true,
        'validation_rules' => 'required|string',
        'order' => 1,
    ]);

    $product = Product::factory()->for($package)->create([
        'name' => 'Product One',
        'is_active' => true,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test('main.buy-now-modal')
        ->call('openPackageOverlay', $package->id)
        ->assertSet('showPackageProducts', true)
        ->call('openBuyNow', $product->id, true, 1)
        ->assertSet('showPackageProducts', false)
        ->assertSet('buyNowProductId', $product->id)
        ->assertSee('Player ID')
        ->assertSee('Product One');
});

it('shows no additional requirements message when package has no requirements', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create([
        'is_active' => true,
        'name' => 'Package No Reqs',
    ]);

    $product = Product::factory()->for($package)->create([
        'name' => 'Simple Product',
        'is_active' => true,
        'order' => 1,
    ]);

    Livewire::actingAs($user)
        ->test('main.buy-now-modal')
        ->call('openPackageOverlay', $package->id)
        ->call('openBuyNow', $product->id, true, 1)
        ->assertSee(__('messages.no_additional_requirements'));
});
