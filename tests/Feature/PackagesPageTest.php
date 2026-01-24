<?php

use App\Models\Category;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('packages page renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/packages')
        ->assertOk()
        ->assertSee('data-test="packages-page"', false);
});

test('packages page lists existing packages', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create([
        'name' => 'Gold Pack',
    ]);

    $this->actingAs($user)
        ->get('/packages')
        ->assertOk()
        ->assertSee($package->name);
});

test('package can be created from manager form', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::backend.packages.index')
        ->set('packageCategoryId', $category->id)
        ->set('packageName', 'Starter Pack')
        ->set('packageDescription', 'Starter description')
        ->set('packageOrder', 1)
        ->set('packageIsActive', true)
        ->call('savePackage')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('packages', [
        'name' => 'Starter Pack',
        'slug' => 'starter-pack',
    ]);
});

test('package requirement can be added to selected package', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::backend.packages.index')
        ->set('selectedPackageId', $package->id)
        ->set('requirementKey', 'player_id')
        ->set('requirementLabel', 'Player ID')
        ->set('requirementType', 'number')
        ->set('requirementIsRequired', true)
        ->set('requirementValidationRules', 'required|numeric')
        ->set('requirementOrder', 1)
        ->call('saveRequirement')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('package_requirements', [
        'package_id' => $package->id,
        'key' => 'player_id',
        'label' => 'Player ID',
        'type' => 'number',
        'is_required' => true,
        'order' => 1,
    ]);
});
