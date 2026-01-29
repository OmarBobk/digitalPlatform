<?php

use App\Models\Category;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('homepage renders main sections and gift cards', function () {
    $package = Package::factory()->create([
        'is_active' => true,
        'order' => 1,
        'image' => null,
    ]);

    Product::factory()->for($package)->create([
        'name' => 'Kablosuz Kulaklık',
        'retail_price' => 1299,
        'is_active' => true,
        'order' => 1,
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('href="'.route('login').'"', false);
    $response->assertSee('data-test="cart-dropdown"', false);
    $response->assertSee('data-test="cart-go-to"', false);
    $response->assertSee('data-test="cart-add"', false);
    $response->assertSee('data-section="homepage-marquee"', false);
    $response->assertSee('data-section="homepage-promos"', false);
    $response->assertSee('data-section="homepage-section-of-categories"', false);
    $response->assertSee('data-section="homepage-section-of-packages"', false);
    $response->assertSee('data-section="homepage-section-of-products"', false);
    $response->assertSee('data-section="homepage-preferences"', false);
    $response->assertSee(__('main.gift_cards'));
    $response->assertSee(__('messages.packages'));
    $response->assertSee(__('main.featured_products'));
    $response->assertSee('APP STORE');
    $response->assertSee('PLAYSTATION');
    $response->assertSee('STEAM');
    $response->assertSee('GOOGLE PLAY');
    $response->assertSee('XBOX');
    $response->assertSee('RAZER GOLD');
    $response->assertSee('AMAZON');
    $response->assertSee('BATTLENET');
    $response->assertSee('Kablosuz Kulaklık');
});

test('homepage circular slider shows packages and placeholder', function () {
    $category = Category::factory()->create(['order' => 1]);

    Package::factory()
        ->count(21)
        ->for($category)
        ->sequence(fn ($sequence) => [
            'name' => 'Package '.($sequence->index + 1),
            'order' => $sequence->index + 1,
            'is_active' => true,
            'image' => $sequence->index === 0 ? null : 'images/packages/package-'.$sequence->index.'.jpg',
        ])
        ->create();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Package 1');
    $response->assertSee('Package 19');
    $response->assertDontSee('Package 20');
    $response->assertDontSee('Package 21');
    $response->assertSee(asset('images/icons/category-placeholder.svg'));
});
