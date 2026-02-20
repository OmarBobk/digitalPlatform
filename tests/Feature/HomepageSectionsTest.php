<?php

it('renders homepage sliders', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeLivewire('main.circular-slider')
        ->assertSeeLivewire('main.promotional-sliders')
        ->assertSee('group-hover:border-accent');
});
