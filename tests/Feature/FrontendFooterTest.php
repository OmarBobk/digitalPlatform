<?php

test('home page shows footer content', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee(__('main.footer_weekly_deals'))
        ->assertSee(__('main.footer_fast_delivery'))
        ->assertSee(config('app.name'));
});
