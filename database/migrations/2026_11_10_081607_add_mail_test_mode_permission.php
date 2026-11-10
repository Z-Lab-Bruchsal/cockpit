<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the permission for the mail test mode page in the settings, so it can be switched and configured in the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('View:MailTestModePage', 'web');

        Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'super_admin')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'View:MailTestModePage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
