<?php

test('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('data-test="frontend-announcement-bar"', false);
    $response->assertSee(__('main.announcement_welcome'), false);
});
