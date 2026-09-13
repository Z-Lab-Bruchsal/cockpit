<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'street', 'zip', 'city', 'logo_path'])]
class CompanySetting extends Model
{
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
