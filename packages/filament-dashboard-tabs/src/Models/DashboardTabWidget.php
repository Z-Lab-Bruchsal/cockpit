<?php

namespace AlexanderGabriel\FilamentDashboardTabs\Models;

use AlexanderGabriel\FilamentDashboardTabs\Database\Factories\DashboardTabWidgetFactory;
use AlexanderGabriel\FilamentDashboardTabs\Enums\WidgetWidth;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dashboard_tab_id
 * @property class-string<Widget> $widget
 * @property WidgetWidth $width
 * @property int $sort
 */
class DashboardTabWidget extends Model
{
    /** @use HasFactory<DashboardTabWidgetFactory> */
    use HasFactory;

    protected $fillable = ['dashboard_tab_id', 'widget', 'width', 'sort'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => WidgetWidth::class,
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DashboardTab, $this>
     */
    public function tab(): BelongsTo
    {
        return $this->belongsTo(DashboardTab::class, 'dashboard_tab_id');
    }

    protected static function newFactory(): DashboardTabWidgetFactory
    {
        return DashboardTabWidgetFactory::new();
    }
}
