<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactFormTopic: string implements HasLabel
{
    case General = 'allgemein';
    case Courses = 'kurse';
    case Schools = 'schulen';
    case LostEmailAccess = 'email-verloren';
    case Privacy = 'datenschutz';

    public function getLabel(): string
    {
        return match ($this) {
            self::General => 'Allgemeine Frage',
            self::Courses => 'Frage zu Kursen',
            self::Schools => 'Anfrage für Schulen',
            self::LostEmailAccess => 'Kein Zugriff mehr auf meine E-Mail-Adresse',
            self::Privacy => 'Konto löschen / Datenschutz',
        };
    }
}
