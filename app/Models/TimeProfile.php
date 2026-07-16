<?php

namespace App\Models;

use Database\Factories\TimeProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeProfile extends Model
{
    /** @use HasFactory<TimeProfileFactory> */
    use HasFactory;

    protected $fillable = ['name', 'weekly_hours', 'description'];

    protected function casts(): array
    {
        return [
            'weekly_hours' => 'decimal:2',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TimeProfileAssignment::class);
    }
}
