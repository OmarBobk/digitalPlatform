<?php

use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('products page renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/products')
        ->assertOk()
        ->assertSee('data-test="products-page"', false);
});

test('products page lists existing products', function () {
    $user = User::factory()->create();
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
