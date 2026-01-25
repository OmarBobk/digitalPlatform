<?php

test('frontend layout uses rtl direction for arabic locale', function () {
    $response = $this->withSession(['locale' => 'ar'])->get('/');

    $response->assertOk();
    $response->assertSee('<html lang="ar" dir="rtl"', false);
});

test('frontend layout uses ltr direction for other locales', function () {
    $response = $this->withSession(['locale' => 'en'])->get('/');

    $response->assertOk();
    $response->assertSee('<html lang="en" dir="ltr"', false);
    $response->assertDontSee('<html lang="en" dir="rtl"', false);
});

test('cart prices stay left-to-right', function () {
    $response = $this->withSession(['locale' => 'ar'])->get('/cart');

    $response->assertOk();
    $response->assertSee('dir="ltr" x-text="$store.cart.format(item.price)"', false);
    $response->assertSee('dir="ltr" x-text="$store.cart.format($store.cart.subtotal)"', false);
});
