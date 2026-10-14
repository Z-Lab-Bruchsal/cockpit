<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactType: string implements HasLabel
{
    case Parent = 'parent';
    case School = 'school';
    /** Sub-account of a school, invited by the school account. */
    case Teacher = 'teacher';

    public function getLabel(): string
    {
        return match ($this) {
            self::Parent => 'Eltern',
            self::School => 'Schule',
            self::Teacher => 'Lehrkraft',
        };
    }
}
