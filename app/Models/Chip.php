<?php

namespace App\Models;

use Database\Factories\ChipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uid', 'user_id', 'label', 'is_active', 'first_seen_at', 'last_seen_at', 'last_seen_terminal_id'])]
class Chip extends Model
{
    /** @use HasFactory<ChipFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Normalize a chip UID as reported by a reader ("04:a1:b2", "04-A1-B2", "04a1b2")
     * to uppercase hex without separators.
     */
    public static function normalizeUid(string $uid): string
    {
        return strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $uid));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastSeenTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'last_seen_terminal_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TerminalEvent::class);
    }
}
