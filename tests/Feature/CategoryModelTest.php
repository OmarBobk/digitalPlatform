<?php

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('categories table has expected columns', function () {
    expect(Schema::hasColumns('categories', [
        'id',
        'parent_id',
        'name',
        'slug',
        'order',
        'icon',
        'is_active',
        'image',
    ]))->toBeTrue();
});

test('category casts flags and order', function () {
    $category = Category::factory()->create([
        'is_active' => 1,
        'order' => 5,
        'parent_id' => null,
    ]);

    expect($category->is_active)->toBeTrue();
    expect($category->order)->toBe(5);
    expect($category->parent_id)->toBeNull();
});

test('category slug defaults from name', function () {
    $category = Category::create([
        'parent_id' => null,
        'name' => 'Phone Cases',
        'order' => 1,
        'icon' => null,
        'is_active' => true,
        'image' => null,
    ]);

    expect($category->slug)->toBe(Str::slug($category->name));
});
