<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RegistrationSource: string implements HasLabel
{
    case Homepage = 'homepage';
    case Cockpit = 'cockpit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Homepage => 'Homepage',
            self::Cockpit => 'Cockpit',
        };
    }
}
