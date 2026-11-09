<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * How families take part in a course instance: book seats, or simply drop by at the given times.
 */
enum CourseParticipation: string implements HasDescription, HasLabel
{
    case Registration = 'registration';
    case DropIn = 'drop_in';

    public function getLabel(): string
    {
        return match ($this) {
            self::Registration => 'Mit Anmeldung',
            self::DropIn => 'Einfach vorbeikommen',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Registration => 'Feste Termine, begrenzte Plätze, Buchung über die Homepage.',
            self::DropIn => 'Regelmäßiges offenes Angebot ohne Anmeldung, z. B. Offenes Tüfteln.',
        };
    }

    /**
     * Name of the filter tab on the homepage.
     */
    public function tabLabel(): string
    {
        return match ($this) {
            self::Registration => 'Kurse & Workshops',
            self::DropIn => 'Einfach vorbeikommen',
        };
    }
}
