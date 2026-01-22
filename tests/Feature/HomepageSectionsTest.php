<?php

it('renders homepage sliders', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeLivewire('landing.circular-slider')
        ->assertSeeLivewire('landing.promotional-sliders')
        ->assertSee('group-hover:border-accent');
});
