<?php

test('homepage renders main sections and gift cards', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('href="'.route('login').'"', false);
    $response->assertSee('data-test="cart-dropdown"', false);
    $response->assertSee('data-test="cart-go-to"', false);
    $response->assertSee('data-test="cart-add"', false);
    $response->assertSee('data-section="homepage-marquee"', false);
    $response->assertSee('data-section="homepage-promos"', false);
    $response->assertSee('data-section="homepage-section-of-categories"', false);
    $response->assertSee('Hediye Kartları');
    $response->assertSee('Öne Çıkan Ürünler');
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
