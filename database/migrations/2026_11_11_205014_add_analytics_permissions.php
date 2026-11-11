<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the permissions for the analytics page and its widgets.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $permissions = [
        'View:AnalyticsPage',
        'View:CourseStatsWidget',
        'View:ParticipantStatsWidget',
        'View:KidAgeChartWidget',
        'View:EnrollmentsPerMonthChartWidget',
        'View:InterestChartWidget',
        'View:TopKidsWidget',
        'View:TopCoursesWidget',
        'View:OrderCostStatsWidget',
        'View:OrderCostsPerMonthChartWidget',
        'View:OrderCostsByMerchantChartWidget',
        'View:WorkHoursWidget',
        'View:WorkHoursPerWeekChartWidget',
    ];

    public function up(): void
    {
        $permissions = collect($this->permissions)->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['super_admin', 'Z-Lab-Boss'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', $this->permissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
