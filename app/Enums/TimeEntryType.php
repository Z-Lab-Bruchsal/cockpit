<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TimeEntryType: string implements HasLabel
{
    case Come = 'come';
    case BreakStart = 'break_start';
    case BreakEnd = 'break_end';
    case Go = 'go';

    public function getLabel(): string
    {
        return match ($this) {
            self::Come => 'Kommen',
            self::BreakStart => 'Pause Start',
            self::BreakEnd => 'Pause Ende',
            self::Go => 'Gehen',
        };
    }
}
