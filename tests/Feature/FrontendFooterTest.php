<?php

test('home page shows footer content', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Haftalık fırsatlar')
        ->assertSee('Hızlı teslimat')
        ->assertSee('indirimGo');
});
