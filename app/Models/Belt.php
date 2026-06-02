<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Belt extends Model
{
    protected $fillable = ['name'];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class);
    }
}
