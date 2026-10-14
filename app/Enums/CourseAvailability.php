<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Registration state of a course instance as shown on the homepage.
 */
enum CourseAvailability: string implements HasColor, HasLabel
{
    case Available = 'available';
    case FewSeatsLeft = 'few_seats_left';
    case Waitlist = 'waitlist';
    case Full = 'full';
    /** No seat free, but some are only held in carts or awaiting confirmation and may become free again. */
    case Reserved = 'reserved';
    case NotYetOpen = 'not_yet_open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Plätze frei',
            self::FewSeatsLeft => 'Nur noch wenige Plätze',
            self::Waitlist => 'Ausgebucht – Warteliste',
            self::Full => 'Ausgebucht',
            self::Reserved => 'Gerade alle Plätze in Reservierung',
            self::NotYetOpen => 'Anmeldung noch nicht geöffnet',
            self::Closed => 'Anmeldung geschlossen',
            self::Cancelled => 'Abgesagt',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::FewSeatsLeft => 'warning',
            self::Waitlist => 'info',
            self::Reserved => 'warning',
            self::Full, self::Cancelled => 'danger',
            self::NotYetOpen, self::Closed => 'gray',
        };
    }

    /**
     * Hex color used for calendar events and badges on the homepage.
     */
    public function hexColor(): string
    {
        return match ($this) {
            self::Available => '#16a34a',
            self::FewSeatsLeft => '#d97706',
            self::Waitlist => '#2563eb',
            self::Reserved => '#9333ea',
            self::Full, self::Cancelled => '#dc2626',
            self::NotYetOpen, self::Closed => '#6b7280',
        };
    }

    public function acceptsRegistrations(): bool
    {
        return in_array($this, [self::Available, self::FewSeatsLeft, self::Waitlist], true);
    }
}
