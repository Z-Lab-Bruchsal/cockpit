<?php

namespace AlexanderGabriel\FilamentDashboardTabs\Concerns;

use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTab;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Add to the user model to give each user their own dashboard tabs.
 */
trait HasDashboardTabs
{
    /**
     * @return HasMany<DashboardTab, $this>
     */
    public function dashboardTabs(): HasMany
    {
        return $this->hasMany(DashboardTab::class, 'user_id')->orderBy('sort')->orderBy('id');
    }
}
