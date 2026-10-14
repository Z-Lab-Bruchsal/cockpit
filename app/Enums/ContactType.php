<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactType: string implements HasLabel
{
    case Parent = 'parent';
    case School = 'school';

    public function getLabel(): string
    {
        return match ($this) {
            self::Parent => 'Eltern',
            self::School => 'Schule',
        };
    }
}
