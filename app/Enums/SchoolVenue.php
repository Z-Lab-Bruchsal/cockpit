<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a booked school visit takes place.
 */
enum SchoolVenue: string implements HasLabel
{
    case ZLab = 'zlab';
    case School = 'school';

    public function getLabel(): string
    {
        return match ($this) {
            self::ZLab => 'Im Z-LAB',
            self::School => 'In der Schule',
        };
    }
}
