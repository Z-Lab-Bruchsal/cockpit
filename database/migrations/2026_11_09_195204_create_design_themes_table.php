<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Homepage colors beyond the brand and accent color: published colors per role, overrides for single elements,
 * an unpublished draft from the design mode and a history to go back to.
 * Also adds the permissions for the design mode and for processing orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_themes', function (Blueprint $table) {
            $table->id();
            $table->json('tokens')->nullable();
            $table->json('element_overrides')->nullable();
            $table->json('draft')->nullable();
            $table->foreignId('draft_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('design_theme_revisions', function (Blueprint $table) {
            $table->id();
            $table->json('snapshot');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        foreach (['Homepage:Design', 'Orders:Process'] as $name) {
            $permission = Permission::findOrCreate($name, 'web');

            Role::query()
                ->where('guard_name', 'web')
                ->whereIn('name', ['super_admin', 'Z-Lab-Boss'])
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('design_theme_revisions');
        Schema::dropIfExists('design_themes');

        DB::table('permissions')->whereIn('name', ['Homepage:Design', 'Orders:Process'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
