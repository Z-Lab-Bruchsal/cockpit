<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Kid extends Model
{
    protected $fillable = ["name", "email"];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class)->withPivot("date");
    }
}
