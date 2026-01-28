<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('package validation uses arabic attribute names', function () {
    $role = Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole($role);

    $category = Category::factory()->create();

    app()->setLocale('ar');

    Livewire::actingAs($admin)
        ->test('pages::backend.packages.index')
        ->set('packageCategoryId', $category->id)
        ->set('packageOrder', 1)
        ->set('packageName', '')
        ->call('savePackage')
        ->assertHasErrors(['packageName' => 'required'])
        ->assertSee('حقل الاسم مطلوب.');
});
