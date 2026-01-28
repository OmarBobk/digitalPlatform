<?php

test('404 page renders helpful content', function () {
    $response = $this->get('/404');

    $response->assertOk();
    $response->assertSee(__('messages.not_found'));
    $response->assertSee(__('messages.page_not_found_message'));
    $response->assertSee(__('messages.homepage'));
});
