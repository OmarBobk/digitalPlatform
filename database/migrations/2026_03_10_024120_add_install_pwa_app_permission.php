<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'install_pwa_app']);
        $admin = Role::findByName('admin');
        if ($admin && ! $admin->hasPermissionTo($permission)) {
            $admin->givePermissionTo($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::findByName('install_pwa_app')?->delete();
    }
};
