<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TerminalEventType: string implements HasLabel
{
    case Book = 'book';
    case Info = 'info';

    public function getLabel(): string
    {
        return match ($this) {
            self::Book => 'Buchung',
            self::Info => 'Auskunft',
        };
    }
}
