<?php

use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->syncPermissions([
        Permission::firstOrCreate(['name' => 'manage_products']),
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

test('products page renders for authenticated user', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get('/products')
        ->assertOk()
        ->assertSee('data-test="products-page"', false);
});

test('products page lists existing products', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $product = Product::factory()->create([
        'name' => 'Bronze Product',
    ]);

    $this->actingAs($user)
        ->get('/products')
        ->assertOk()
        ->assertSee($product->name);
});

test('product can be created from manager form', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $package = Package::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::backend.products.index')
        ->set('productPackageId', $package->id)
        ->set('productSerial', 'SER-10001')
        ->set('productName', 'Starter Product')
        ->set('productRetailPrice', '10.50')
        ->set('productWholesalePrice', '7.25')
        ->set('productOrder', 1)
        ->set('productIsActive', true)
        ->call('saveProduct')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('products', [
        'name' => 'Starter Product',
        'slug' => 'starter-product',
        'serial' => 'SER-10001',
    ]);
});
