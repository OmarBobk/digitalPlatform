<?php

use App\Enums\ProductAmountMode;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view_dashboard']);
    Permission::firstOrCreate(['name' => 'update_product_prices']);
    Permission::firstOrCreate(['name' => 'manage_products']);
});

test('guest cannot access product entry prices page', function () {
    $this->get('/product-entry-prices')->assertRedirect();
});

test('user without permission cannot access product entry prices page', function () {
    $role = Role::firstOrCreate(['name' => 'seller']);
    $role->syncPermissions(['view_dashboard']);

    $user = User::factory()->create();
    $user->assignRole('seller');

    $this->actingAs($user)->get('/product-entry-prices')->assertForbidden();
});

test('user with manage_products but without update_product_prices cannot access page', function () {
    $role = Role::firstOrCreate(['name' => 'catalog_only']);
    $role->syncPermissions(['view_dashboard', 'manage_products']);

    $user = User::factory()->create();
    $user->assignRole('catalog_only');

    $this->actingAs($user)->get('/product-entry-prices')->assertForbidden();
});

test('user with permission can view product entry prices page', function () {
    $role = Role::firstOrCreate(['name' => 'price_editor']);
    $role->syncPermissions(['view_dashboard', 'update_product_prices']);

    $user = User::factory()->create();
    $user->assignRole('price_editor');

    $this->actingAs($user)
        ->get('/product-entry-prices')
        ->assertOk()
        ->assertSee('data-test="product-entry-prices-page"', false);
});

test('user can update entry price for a product in selected package', function () {
    $role = Role::firstOrCreate(['name' => 'price_editor']);
    $role->syncPermissions(['view_dashboard', 'update_product_prices']);

    $user = User::factory()->create();
    $user->assignRole('price_editor');

    $package = Package::factory()->create();
    $product = Product::factory()->for($package)->create([
        'entry_price' => 5,
        'amount_mode' => ProductAmountMode::Fixed,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::backend.product-entry-prices.index')
        ->set('packageId', (string) $package->id)
        ->set('newPrices.'.(string) $product->id, '12.50')
        ->call('saveChangedPrices')
        ->assertHasNoErrors();

    expect((float) $product->fresh()->entry_price)->toBe(12.5);
});

test('custom amount product rejects non positive entry price on update', function () {
    $role = Role::firstOrCreate(['name' => 'price_editor']);
    $role->syncPermissions(['view_dashboard', 'update_product_prices']);

    $user = User::factory()->create();
    $user->assignRole('price_editor');

    $package = Package::factory()->create();
    $product = Product::factory()->for($package)->create([
        'entry_price' => 0.05,
        'amount_mode' => ProductAmountMode::Custom,
        'amount_unit_label' => 'GB',
        'custom_amount_min' => 1,
        'custom_amount_max' => 100,
        'custom_amount_step' => 1,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::backend.product-entry-prices.index')
        ->set('packageId', (string) $package->id)
        ->set('newPrices.'.(string) $product->id, '0')
        ->call('saveChangedPrices')
        ->assertHasErrors(['newPrices.'.(string) $product->id]);
});
