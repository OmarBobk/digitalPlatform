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

        Permission::firstOrCreate(['name' => 'manage_bugs']);

        $admin = Role::query()->where('name', 'admin')->first();
        if ($admin !== null) {
            $admin->givePermissionTo('manage_bugs');
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::query()->where('name', 'admin')->first();
        if ($admin !== null && $admin->hasPermissionTo('manage_bugs')) {
            $admin->revokePermissionTo('manage_bugs');
        }

        Permission::query()->where('name', 'manage_bugs')->delete();
    }
};
