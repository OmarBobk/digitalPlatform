<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'update_product_prices']);

        $admin = Role::query()->where('name', 'admin')->first();
        if ($admin !== null) {
            $admin->givePermissionTo('update_product_prices');
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::query()->where('name', 'admin')->first();
        if ($admin !== null && $admin->hasPermissionTo('update_product_prices')) {
            $admin->revokePermissionTo('update_product_prices');
        }

        Permission::query()->where('name', 'update_product_prices')->delete();
    }
};
