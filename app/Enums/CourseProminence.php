<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How prominently a course instance is shown in offer lists on the homepage.
 */
enum CourseProminence: string implements HasLabel
{
    case Standard = 'standard';
    case Featured = 'featured';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standard => 'Normale Karte',
            self::Featured => 'Große Karte (hervorgehoben)',
        };
    }
}
