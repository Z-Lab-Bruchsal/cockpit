<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CourseAudience: string implements HasLabel
{
    case Kids = 'kids';
    case SchoolClass = 'school_class';

    public function getLabel(): string
    {
        return match ($this) {
            self::Kids => 'Kinder & Jugendliche',
            self::SchoolClass => 'Schulklassen',
        };
    }
}
