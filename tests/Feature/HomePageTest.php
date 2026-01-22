<?php

test('homepage renders marquee and promo sections', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('data-section="homepage-marquee"', false);
    $response->assertSee('data-section="homepage-promos"', false);
});
