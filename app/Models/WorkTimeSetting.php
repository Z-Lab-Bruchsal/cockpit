<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkTimeSetting extends Model
{
    protected $fillable = [
        'threshold_1_hours',
        'break_1_minutes',
        'threshold_2_hours',
        'break_2_minutes',
        'minimum_qualifying_break_minutes',
    ];

    protected function casts(): array
    {
        return [
            'threshold_1_hours' => 'decimal:2',
            'threshold_2_hours' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'threshold_1_hours' => 6,
            'break_1_minutes' => 30,
            'threshold_2_hours' => 9,
            'break_2_minutes' => 45,
            'minimum_qualifying_break_minutes' => 15,
        ]);
    }
}
