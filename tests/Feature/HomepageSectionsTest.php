<?php

use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders homepage sliders', function () {
    Package::factory()->create([
        'name' => 'Test Package',
        'is_active' => true,
        'image' => null,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSeeLivewire('main.circular-slider')
        ->assertSeeLivewire('main.promotional-sliders')
        ->assertSee('group-hover:border-accent')
        ->assertSee('data-test="circular-slider-item"', false)
        ->assertSee('onerror="this.onerror=null; this.src=', false);
});
