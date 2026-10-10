<?php

namespace App\Models;

use App\Enums\TerminalEventResult;
use App\Enums\TerminalEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['terminal_id', 'chip_uid', 'chip_id', 'user_id', 'type', 'result', 'booking_id'])]
class TerminalEvent extends Model
{
    protected function casts(): array
    {
        return [
            'type' => TerminalEventType::class,
            'result' => TerminalEventResult::class,
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function chip(): BelongsTo
    {
        return $this->belongsTo(Chip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
