<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BookingSource: string implements HasLabel
{
    case WebLive = 'web_live';
    case WebManual = 'web_manual';
    case Terminal = 'terminal';

    public function getLabel(): string
    {
        return match ($this) {
            self::WebLive => 'Cockpit live',
            self::WebManual => 'Cockpit manuell',
            self::Terminal => 'Stechuhr',
        };
    }
}
