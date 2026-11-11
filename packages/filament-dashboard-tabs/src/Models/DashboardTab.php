<?php

namespace AlexanderGabriel\FilamentDashboardTabs\Models;

use AlexanderGabriel\FilamentDashboardTabs\Database\Factories\DashboardTabFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $sort
 * @property array<string, mixed>|null $filters
 */
class DashboardTab extends Model
{
    /** @use HasFactory<DashboardTabFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'sort', 'filters'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'filters' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(static::userModel());
    }

    /**
     * @return HasMany<DashboardTabWidget, $this>
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardTabWidget::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return class-string<Model>
     */
    public static function userModel(): string
    {
        return config('filament-dashboard-tabs.user_model')
            ?? config('auth.providers.'.config('auth.guards.'.config('auth.defaults.guard').'.provider').'.model');
    }

    protected static function newFactory(): DashboardTabFactory
    {
        return DashboardTabFactory::new();
    }
}
