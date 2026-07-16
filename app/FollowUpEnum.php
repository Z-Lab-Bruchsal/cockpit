<?php

namespace App;

enum FollowUp: string
{
    case TOMORROW = 'tomorrow';
    case MONDAY = 'monday';
    case TUESDAY = 'tuesday';
    case WEDNESDAY = 'wednesday';
    case THURSTDAY = 'hursday';
    case FRIDAY = 'friday';
    case TWO_WEEKS = 'two_weeks';

    public function label(): string
    {
        return match ($this) {
            self::TOMORROW => 'Morgen',
            self::MONDAY => 'Montag',
            self::TUESDAY => 'Dienstag',
            self::WEDNESDAY => 'Mittwoch',
            self::THURSTDAY => 'Donnerstag',
            self::FRIDAY => 'Freitag',
            self::TWO_WEEKS => 'In 2 Wochen',
        };
    }
}
