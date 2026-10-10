<?php

namespace App\Models;

use Database\Factories\TerminalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'username', 'password', 'is_active', 'last_seen_at', 'ip_address'])]
#[Hidden(['password'])]
class Terminal extends Model
{
    /** @use HasFactory<TerminalFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(TerminalEvent::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
