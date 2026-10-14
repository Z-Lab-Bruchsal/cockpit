<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SectionLayout: string implements HasLabel
{
    case Standard = 'standard';
    case Highlighted = 'highlighted';
    case FullWidth = 'full_width';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Highlighted => 'Hervorgehoben (farbig)',
            self::FullWidth => 'Breit (ganze Zeile)',
        };
    }
}
