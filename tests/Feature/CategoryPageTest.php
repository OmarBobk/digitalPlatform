<?php

use App\Models\Category;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows active packages for a category page', function () {
    $category = Category::factory()->create([
        'name' => 'Game Topups',
        'is_active' => true,
        'parent_id' => null,
    ]);

    Package::factory()->create([
        'category_id' => $category->id,
        'name' => 'Visible Pack',
        'is_active' => true,
        'order' => 1,
    ]);

    Package::factory()->create([
        'category_id' => $category->id,
        'name' => 'Hidden Pack',
        'is_active' => false,
        'order' => 2,
    ]);

    $this->get(route('categories.show', ['category' => $category->slug]))
        ->assertOk()
        ->assertSeeLivewire('pages::frontend.category-show')
        ->assertSee('Game Topups')
        ->assertSee('Visible Pack')
        ->assertDontSee('Hidden Pack')
        ->assertSee(__('main.breadcrumb_home'))
        ->assertSee(__('main.breadcrumb_categories'))
        ->assertSee(__('main.category_packages_count', ['count' => 1]))
        ->assertSee(__('main.view_mode_grid'))
        ->assertSee(__('main.view_mode_list'))
        ->assertSee('data-test="category-page-package-count-chip"', false);
});

it('returns 404 for inactive category pages', function () {
    $inactiveCategory = Category::factory()->create([
        'is_active' => false,
        'parent_id' => null,
    ]);

    $this->get(route('categories.show', ['category' => $inactiveCategory->slug]))
        ->assertNotFound();
});
