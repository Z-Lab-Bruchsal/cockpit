<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CourseCardStyle: string implements HasLabel
{
    case ImageTop = 'image_top';
    case ImageLeft = 'image_left';
    case Compact = 'compact';

    public function getLabel(): string
    {
        return match ($this) {
            self::ImageTop => 'Bild oben',
            self::ImageLeft => 'Bild links',
            self::Compact => 'Kompakt (ohne Bild)',
        };
    }
}
