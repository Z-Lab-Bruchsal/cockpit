<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
    protected $fillable = ['name', 'duration'];

    public function kids(): BelongsToMany
    {
        return $this->belongsToMany(Kid::class)->withPivot('date');
    }

    public function belts(): BelongsToMany
    {
        return $this->belongsToMany(Belt::class);
    }
}
