<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CourseDetailLayout: string implements HasLabel
{
    case HeroImage = 'hero_image';
    case SideImage = 'side_image';
    case Minimal = 'minimal';

    public function getLabel(): string
    {
        return match ($this) {
            self::HeroImage => 'Großes Titelbild',
            self::SideImage => 'Bild neben dem Text',
            self::Minimal => 'Ohne großes Bild',
        };
    }
}
