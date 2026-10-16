<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Type of school a class comes from; school class courses can be limited to some of them.
 */
enum SchoolType: string implements HasLabel
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Intermediate = 'intermediate';
    case Grammar = 'grammar';
    case Comprehensive = 'comprehensive';
    case SpecialNeeds = 'special_needs';
    case Vocational = 'vocational';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Primary => 'Grundschule',
            self::Secondary => 'Mittelschule / Hauptschule',
            self::Intermediate => 'Realschule',
            self::Grammar => 'Gymnasium',
            self::Comprehensive => 'Gesamtschule',
            self::SpecialNeeds => 'Förderschule',
            self::Vocational => 'Berufliche Schule',
            self::Other => 'Andere Schulart',
        };
    }
}
