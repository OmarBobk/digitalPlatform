<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('categories page renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/categories')
        ->assertOk()
        ->assertSee('data-test="categories-page"', false);
});

test('categories page lists existing categories', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create([
        'name' => 'Accessories',
    ]);

    $this->actingAs($user)
        ->get('/categories')
        ->assertOk()
        ->assertSee($category->name);
});

test('order placeholder reflects current order range', function () {
    $user = User::factory()->create();

    Category::factory()->create(['order' => 2]);
    Category::factory()->create(['order' => 9]);

    $this->actingAs($user)
        ->get('/categories')
        ->assertOk()
        ->assertSee('placeholder="2 - 9"', false);
});

test('category can be created from manager form', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::backend.categories.index')
        ->set('newName', 'Chargers')
        ->set('newOrder', 1)
        ->set('newIsActive', true)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'name' => 'Chargers',
        'slug' => 'chargers',
    ]);
});

test('category can be edited from manager form', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create([
        'name' => 'Adapters',
        'order' => 7,
        'is_active' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::backend.categories.index')
        ->call('startEdit', $category->id)
        ->set('newName', 'Adapters Updated')
        ->set('newOrder', 7)
        ->set('newIsActive', false)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Adapters Updated',
        'is_active' => false,
    ]);
});

test('category order must be unique', function () {
    $user = User::factory()->create();
    Category::factory()->create(['order' => 3]);

    $this->actingAs($user);

    Livewire::test('pages::backend.categories.index')
        ->set('newName', 'Adapters')
        ->set('newOrder', 3)
        ->set('newIsActive', true)
        ->call('save')
        ->assertHasErrors(['newOrder' => 'unique']);
});

test('status filter can show inactive categories only', function () {
    $user = User::factory()->create();
    $activeCategory = Category::factory()->create(['name' => 'Active Category X', 'is_active' => true]);
    $inactiveCategory = Category::factory()->create(['name' => 'Inactive Category Y', 'is_active' => false]);

    $this->actingAs($user);

    $component = Livewire::test('pages::backend.categories.index')
        ->set('statusFilter', 'inactive');

    $names = $component->get('categories')->pluck('name')->all();

    expect($names)->toContain($inactiveCategory->name);
    expect($names)->not->toContain($activeCategory->name);
});

test('category image is stored when uploaded', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::backend.categories.index')
        ->set('newName', 'Cables')
        ->set('newOrder', 2)
        ->set('newIsActive', true)
        ->set('newImageFile', UploadedFile::fake()->image('category.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $category = Category::query()->firstOrFail();

    expect($category->image)->not->toBeNull();
    expect(File::exists(public_path($category->image)))->toBeTrue();
    File::delete(public_path($category->image));
});

test('category status can be toggled', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['is_active' => true]);

    $this->actingAs($user);

    Livewire::test('pages::backend.categories.index')
        ->call('toggleStatus', $category->id);

    $category->refresh();

    expect($category->is_active)->toBeFalse();
});

test('deleting a category removes its descendants', function () {
    $user = User::factory()->create();

    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);
    $grandchild = Category::factory()->create(['parent_id' => $child->id]);

    $this->actingAs($user);

    Livewire::test('pages::backend.categories.index')
        ->call('deleteCategory', $parent->id);

    $this->assertDatabaseMissing('categories', ['id' => $parent->id]);
    $this->assertDatabaseMissing('categories', ['id' => $child->id]);
    $this->assertDatabaseMissing('categories', ['id' => $grandchild->id]);
});

test('resetting create form clears inputs', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::backend.categories.index')
        ->set('newName', 'Adapters')
        ->set('newOrder', 5)
        ->set('newIcon', 'bolt')
        ->set('newImageFile', UploadedFile::fake()->image('temp.png'))
        ->set('newIsActive', false)
        ->call('resetCreateForm')
        ->assertSet('newName', '')
        ->assertSet('newOrder', null)
        ->assertSet('newIcon', null)
        ->assertSet('newImageFile', null)
        ->assertSet('newIsActive', true);
});
