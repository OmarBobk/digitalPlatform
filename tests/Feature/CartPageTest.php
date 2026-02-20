<?php

test('cart page renders', function () {
    $response = $this->get('/cart');

    $response->assertOk();
    $response->assertSee(__('main.shopping_cart'));
    $response->assertSee('data-test="cart-page"', false);
});
test('homepage still renders', function () {
    $response = $this->get('/');

    $response->assertOk();
});
