<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $tenants = '[]';
        $users = '[]';
        $userTenantPivot = '[]';
        $rolesWithPermissions = '[{"name":"super_admin","guard_name":"web","permissions":["Create:Belt","Create:Course","Create:Group","Create:Kid","Create:Notes","Create:Order","Create:Role","Create:TimeEntry","Create:TimeProfile","Create:Todo","Create:User","Delete:Belt","Delete:Course","Delete:Group","Delete:Kid","Delete:Notes","Delete:Order","Delete:Role","Delete:TimeEntry","Delete:TimeProfile","Delete:Todo","Delete:User","DeleteAny:Belt","DeleteAny:Course","DeleteAny:Group","DeleteAny:Kid","DeleteAny:Notes","DeleteAny:Order","DeleteAny:Role","DeleteAny:TimeEntry","DeleteAny:TimeProfile","DeleteAny:Todo","DeleteAny:User","ForceDelete:Belt","ForceDelete:Course","ForceDelete:Group","ForceDelete:Kid","ForceDelete:Notes","ForceDelete:Order","ForceDelete:Role","ForceDelete:TimeEntry","ForceDelete:TimeProfile","ForceDelete:Todo","ForceDelete:User","ForceDeleteAny:Belt","ForceDeleteAny:Course","ForceDeleteAny:Group","ForceDeleteAny:Kid","ForceDeleteAny:Notes","ForceDeleteAny:Order","ForceDeleteAny:Role","ForceDeleteAny:TimeEntry","ForceDeleteAny:TimeProfile","ForceDeleteAny:Todo","ForceDeleteAny:User","Reorder:Belt","Reorder:Course","Reorder:Group","Reorder:Kid","Reorder:Notes","Reorder:Order","Reorder:Role","Reorder:TimeEntry","Reorder:TimeProfile","Reorder:Todo","Reorder:User","Replicate:Belt","Replicate:Course","Replicate:Group","Replicate:Kid","Replicate:Notes","Replicate:Order","Replicate:Role","Replicate:TimeEntry","Replicate:TimeProfile","Replicate:Todo","Replicate:User","Restore:Belt","Restore:Course","Restore:Group","Restore:Kid","Restore:Notes","Restore:Order","Restore:Role","Restore:TimeEntry","Restore:TimeProfile","Restore:Todo","Restore:User","RestoreAny:Belt","RestoreAny:Course","RestoreAny:Group","RestoreAny:Kid","RestoreAny:Notes","RestoreAny:Order","RestoreAny:Role","RestoreAny:TimeEntry","RestoreAny:TimeProfile","RestoreAny:Todo","RestoreAny:User","Update:Belt","Update:Course","Update:Group","Update:Kid","Update:Notes","Update:Order","Update:Role","Update:TimeEntry","Update:TimeProfile","Update:Todo","Update:User","View:Belt","View:Course","View:Group","View:Kid","View:MyOrdersWidget","View:MyTodosWidget","View:Notes","View:Order","View:Role","View:TimeClockWidget","View:TimeEntry","View:TimeProfile","View:Todo","View:User","View:WorkTimeCalendarPage","View:WorkTimeSettingsPage","ViewAny:Belt","ViewAny:Course","ViewAny:Group","ViewAny:Kid","ViewAny:Notes","ViewAny:Order","ViewAny:Role","ViewAny:TimeEntry","ViewAny:TimeProfile","ViewAny:Todo","ViewAny:User"]},{"name":"Z-Lab-Boss","guard_name":"web","permissions":["ViewAny:Belt","View:Belt","Create:Belt","Update:Belt","Delete:Belt","DeleteAny:Belt","Restore:Belt","ForceDelete:Belt","ForceDeleteAny:Belt","RestoreAny:Belt","Replicate:Belt","Reorder:Belt","ViewAny:Course","View:Course","Create:Course","Update:Course","Delete:Course","DeleteAny:Course","Restore:Course","ForceDelete:Course","ForceDeleteAny:Course","RestoreAny:Course","Replicate:Course","Reorder:Course","ViewAny:Group","View:Group","Create:Group","Update:Group","Delete:Group","DeleteAny:Group","Restore:Group","ForceDelete:Group","ForceDeleteAny:Group","RestoreAny:Group","Replicate:Group","Reorder:Group","ViewAny:Kid","View:Kid","Create:Kid","Update:Kid","Delete:Kid","DeleteAny:Kid","Restore:Kid","ForceDelete:Kid","ForceDeleteAny:Kid","RestoreAny:Kid","Replicate:Kid","Reorder:Kid","ViewAny:Notes","View:Notes","Create:Notes","Update:Notes","Delete:Notes","DeleteAny:Notes","Restore:Notes","ForceDelete:Notes","ForceDeleteAny:Notes","RestoreAny:Notes","Replicate:Notes","Reorder:Notes","ViewAny:Order","View:Order","Create:Order","Update:Order","Delete:Order","DeleteAny:Order","Restore:Order","ForceDelete:Order","ForceDeleteAny:Order","RestoreAny:Order","Replicate:Order","Reorder:Order","ViewAny:TimeEntry","View:TimeEntry","Create:TimeEntry","Update:TimeEntry","Delete:TimeEntry","DeleteAny:TimeEntry","Restore:TimeEntry","ForceDelete:TimeEntry","ForceDeleteAny:TimeEntry","RestoreAny:TimeEntry","Replicate:TimeEntry","Reorder:TimeEntry","ViewAny:TimeProfile","View:TimeProfile","Create:TimeProfile","Update:TimeProfile","Delete:TimeProfile","DeleteAny:TimeProfile","Restore:TimeProfile","ForceDelete:TimeProfile","ForceDeleteAny:TimeProfile","RestoreAny:TimeProfile","Replicate:TimeProfile","Reorder:TimeProfile","ViewAny:Todo","View:Todo","Create:Todo","Update:Todo","Delete:Todo","DeleteAny:Todo","Restore:Todo","ForceDelete:Todo","ForceDeleteAny:Todo","RestoreAny:Todo","Replicate:Todo","Reorder:Todo","ViewAny:User","View:User","Create:User","Update:User","Delete:User","DeleteAny:User","Restore:User","ForceDelete:User","ForceDeleteAny:User","RestoreAny:User","Replicate:User","Reorder:User","ViewAny:Role","View:Role","Create:Role","Update:Role","Delete:Role","DeleteAny:Role","Restore:Role","ForceDelete:Role","ForceDeleteAny:Role","RestoreAny:Role","Replicate:Role","Reorder:Role","View:WorkTimeCalendarPage","View:WorkTimeSettingsPage","View:TimeClockWidget","View:MyTodosWidget","View:MyOrdersWidget"]},{"name":"Z-Lab-Crew","guard_name":"web","permissions":["ViewAny:Belt","View:Belt","Create:Belt","Update:Belt","Delete:Belt","DeleteAny:Belt","Restore:Belt","ForceDelete:Belt","ForceDeleteAny:Belt","RestoreAny:Belt","Replicate:Belt","Reorder:Belt","ViewAny:Course","View:Course","Create:Course","Update:Course","Delete:Course","DeleteAny:Course","Restore:Course","ForceDelete:Course","ForceDeleteAny:Course","RestoreAny:Course","Replicate:Course","Reorder:Course","ViewAny:Group","View:Group","Create:Group","Update:Group","Delete:Group","DeleteAny:Group","Restore:Group","ForceDelete:Group","ForceDeleteAny:Group","RestoreAny:Group","Replicate:Group","Reorder:Group","ViewAny:Kid","View:Kid","Create:Kid","Update:Kid","Delete:Kid","DeleteAny:Kid","Restore:Kid","ForceDelete:Kid","ForceDeleteAny:Kid","RestoreAny:Kid","Replicate:Kid","Reorder:Kid","ViewAny:Notes","View:Notes","Create:Notes","Update:Notes","Delete:Notes","DeleteAny:Notes","Restore:Notes","ForceDelete:Notes","ForceDeleteAny:Notes","RestoreAny:Notes","Replicate:Notes","Reorder:Notes","ViewAny:Order","View:Order","Create:Order","Update:Order","Delete:Order","DeleteAny:Order","Restore:Order","ForceDelete:Order","ForceDeleteAny:Order","RestoreAny:Order","Replicate:Order","Reorder:Order","ViewAny:TimeEntry","View:TimeEntry","Create:TimeEntry","Update:TimeEntry","Delete:TimeEntry","DeleteAny:TimeEntry","Restore:TimeEntry","ForceDelete:TimeEntry","ForceDeleteAny:TimeEntry","RestoreAny:TimeEntry","Replicate:TimeEntry","Reorder:TimeEntry","ViewAny:Todo","View:Todo","Create:Todo","Update:Todo","Delete:Todo","DeleteAny:Todo","Restore:Todo","ForceDelete:Todo","ForceDeleteAny:Todo","RestoreAny:Todo","Replicate:Todo","Reorder:Todo","View:WorkTimeCalendarPage","View:WorkTimeSettingsPage","View:TimeClockWidget","View:MyTodosWidget","View:MyOrdersWidget"]}]';
        $directPermissions = '[]';

        // 1. Seed tenants first (if present)
        if (! blank($tenants) && $tenants !== '[]') {
            static::seedTenants($tenants);
        }

        // 2. Seed roles with permissions
        static::makeRolesWithPermissions($rolesWithPermissions);

        // 3. Seed direct permissions
        static::makeDirectPermissions($directPermissions);

        // 4. Seed users with their roles/permissions (if present)
        if (! blank($users) && $users !== '[]') {
            static::seedUsers($users);
        }

        // 5. Seed user-tenant pivot (if present)
        if (! blank($userTenantPivot) && $userTenantPivot !== '[]') {
            static::seedUserTenantPivot($userTenantPivot);
        }

        $this->command->info('Shield Seeding Completed.');
    }

    protected static function seedTenants(string $tenants): void
    {
        if (blank($tenantData = json_decode($tenants, true))) {
            return;
        }

        $tenantModel = '';
        if (blank($tenantModel)) {
            return;
        }

        foreach ($tenantData as $tenant) {
            $tenantModel::firstOrCreate(
                ['id' => $tenant['id']],
                $tenant
            );
        }
    }

    protected static function seedUsers(string $users): void
    {
        if (blank($userData = json_decode($users, true))) {
            return;
        }

        $userModel = 'App\Models\User';
        $tenancyEnabled = false;

        foreach ($userData as $data) {
            // Extract role/permission data before creating user
            $roles = $data['roles'] ?? [];
            $permissions = $data['permissions'] ?? [];
            $tenantRoles = $data['tenant_roles'] ?? [];
            $tenantPermissions = $data['tenant_permissions'] ?? [];
            unset($data['roles'], $data['permissions'], $data['tenant_roles'], $data['tenant_permissions']);

            $user = $userModel::firstOrCreate(
                ['email' => $data['email']],
                $data
            );

            // Handle tenancy mode - sync roles/permissions per tenant
            if ($tenancyEnabled && (! empty($tenantRoles) || ! empty($tenantPermissions))) {
                foreach ($tenantRoles as $tenantId => $roleNames) {
                    $contextId = $tenantId === '_global' ? null : $tenantId;
                    setPermissionsTeamId($contextId);
                    $user->syncRoles($roleNames);
                }

                foreach ($tenantPermissions as $tenantId => $permissionNames) {
                    $contextId = $tenantId === '_global' ? null : $tenantId;
                    setPermissionsTeamId($contextId);
                    $user->syncPermissions($permissionNames);
                }
            } else {
                // Non-tenancy mode
                if (! empty($roles)) {
                    $user->syncRoles($roles);
                }

                if (! empty($permissions)) {
                    $user->syncPermissions($permissions);
                }
            }
        }
    }

    protected static function seedUserTenantPivot(string $pivot): void
    {
        if (blank($pivotData = json_decode($pivot, true))) {
            return;
        }

        $pivotTable = '';
        if (blank($pivotTable)) {
            return;
        }

        foreach ($pivotData as $row) {
            $uniqueKeys = [];

            if (isset($row['user_id'])) {
                $uniqueKeys['user_id'] = $row['user_id'];
            }

            $tenantForeignKey = 'team_id';
            if (! blank($tenantForeignKey) && isset($row[$tenantForeignKey])) {
                $uniqueKeys[$tenantForeignKey] = $row[$tenantForeignKey];
            }

            if (! empty($uniqueKeys)) {
                DB::table($pivotTable)->updateOrInsert($uniqueKeys, $row);
            }
        }
    }

    protected static function makeRolesWithPermissions(string $rolesWithPermissions): void
    {
        if (blank($rolePlusPermissions = json_decode($rolesWithPermissions, true))) {
            return;
        }

        /** @var Model $roleModel */
        $roleModel = Utils::getRoleModel();
        /** @var Model $permissionModel */
        $permissionModel = Utils::getPermissionModel();

        $tenancyEnabled = false;
        $teamForeignKey = 'team_id';

        foreach ($rolePlusPermissions as $rolePlusPermission) {
            $tenantId = $rolePlusPermission[$teamForeignKey] ?? null;

            // Set tenant context for role creation and permission sync
            if ($tenancyEnabled) {
                setPermissionsTeamId($tenantId);
            }

            $roleData = [
                'name' => $rolePlusPermission['name'],
                'guard_name' => $rolePlusPermission['guard_name'],
            ];

            // Include tenant ID in role data (can be null for global roles)
            if ($tenancyEnabled && ! blank($teamForeignKey)) {
                $roleData[$teamForeignKey] = $tenantId;
            }

            $role = $roleModel::firstOrCreate($roleData);

            if (! blank($rolePlusPermission['permissions'])) {
                $permissionModels = collect($rolePlusPermission['permissions'])
                    ->map(fn ($permission) => $permissionModel::firstOrCreate([
                        'name' => $permission,
                        'guard_name' => $rolePlusPermission['guard_name'],
                    ]))
                    ->all();

                $role->syncPermissions($permissionModels);
            }
        }
    }

    public static function makeDirectPermissions(string $directPermissions): void
    {
        if (blank($permissions = json_decode($directPermissions, true))) {
            return;
        }

        /** @var Model $permissionModel */
        $permissionModel = Utils::getPermissionModel();

        foreach ($permissions as $permission) {
            if ($permissionModel::whereName($permission['name'])->doesntExist()) {
                $permissionModel::create([
                    'name' => $permission['name'],
                    'guard_name' => $permission['guard_name'],
                ]);
            }
        }
    }
}
