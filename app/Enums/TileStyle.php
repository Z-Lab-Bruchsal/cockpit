<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Look of a tile in a tile grid block.
 */
enum TileStyle: string implements HasLabel
{
    case Standard = 'standard';
    case Highlighted = 'highlighted';
    case Hero = 'hero';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Highlighted => 'Hervorgehoben (farbig)',
            self::Hero => 'Groß mit Überschrift',
        };
    }
}
