<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a school class course takes place. With Both, the teacher chooses when booking.
 */
enum SchoolCourseFormat: string implements HasLabel
{
    case AtZLab = 'at_zlab';
    case AtSchool = 'at_school';
    case Both = 'both';

    public function getLabel(): string
    {
        return match ($this) {
            self::AtZLab => 'Nur im Z-LAB',
            self::AtSchool => 'Nur in der Schule',
            self::Both => 'Im Z-LAB oder in der Schule (Schule wählt)',
        };
    }

    /**
     * @return list<SchoolVenue>
     */
    public function venues(): array
    {
        return match ($this) {
            self::AtZLab => [SchoolVenue::ZLab],
            self::AtSchool => [SchoolVenue::School],
            self::Both => [SchoolVenue::ZLab, SchoolVenue::School],
        };
    }
}
