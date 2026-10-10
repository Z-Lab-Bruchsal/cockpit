<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TerminalEventResult: string implements HasColor, HasLabel
{
    case ClockIn = 'clock_in';
    case ClockOut = 'clock_out';
    case Debounced = 'debounced';
    case UnknownChip = 'unknown_chip';
    case InactiveChip = 'inactive_chip';
    case Info = 'info';

    public function getLabel(): string
    {
        return match ($this) {
            self::ClockIn => 'Kommen',
            self::ClockOut => 'Gehen',
            self::Debounced => 'Doppelt gestempelt',
            self::UnknownChip => 'Unbekannter Chip',
            self::InactiveChip => 'Gesperrter Chip',
            self::Info => 'Auskunft',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ClockIn, self::ClockOut, self::Info => 'success',
            self::Debounced => 'warning',
            self::UnknownChip, self::InactiveChip => 'danger',
        };
    }
}
