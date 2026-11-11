<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the permission for the central team page, where the team shown on the homepage is maintained and ordered.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('Homepage:Team', 'web');

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['super_admin', 'Z-Lab-Boss'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'Homepage:Team')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
