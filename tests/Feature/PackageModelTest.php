<?php

use App\Models\Category;
use App\Models\Package;
use App\Models\PackageRequirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('packages table has expected columns', function () {
    expect(Schema::hasColumns('packages', [
        'id',
        'category_id',
        'name',
        'slug',
        'description',
        'is_active',
        'order',
        'icon',
        'image',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('package slug defaults from name', function () {
    $category = Category::factory()->create();

    $package = Package::create([
        'category_id' => $category->id,
        'name' => 'Starter Bundle',
        'order' => 1,
        'description' => 'Base package.',
        'is_active' => true,
        'icon' => 'box',
    ]);

    expect($package->slug)->toBe(Str::slug($package->name));
});

test('package belongs to category', function () {
    $category = Category::factory()->create();
    $package = Package::factory()->create(['category_id' => $category->id]);

    expect($package->category)->not->toBeNull();
    expect($package->category->is($category))->toBeTrue();
});

test('package has many requirements', function () {
    $package = Package::factory()->create();
    $requirement = PackageRequirement::factory()->create(['package_id' => $package->id]);

    expect($package->requirements->pluck('id'))->toContain($requirement->id);
});
