<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * State of a single seat. Held, Pending and Offered occupy a seat until their expires_at,
 * Confirmed and AwaitingApproval occupy it permanently, Waitlisted does not occupy a seat.
 */
enum EnrollmentStatus: string implements HasColor, HasLabel
{
    case Held = 'held';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    /** Sibling registration beyond the free seats, waiting for the course lead. */
    case AwaitingApproval = 'awaiting_approval';
    case Waitlisted = 'waitlisted';
    /** Moved up from the waitlist, waiting for the parents to accept the seat. */
    case Offered = 'offered';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Held => 'Reserviert',
            self::Pending => 'Wartet auf Bestätigung',
            self::Confirmed => 'Angemeldet',
            self::AwaitingApproval => 'Wartet auf Freigabe',
            self::Waitlisted => 'Warteliste',
            self::Offered => 'Nachrücker – Platz angeboten',
            self::Cancelled => 'Storniert',
            self::Expired => 'Verfallen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Held, self::Pending, self::Offered, self::AwaitingApproval => 'warning',
            self::Confirmed => 'success',
            self::Waitlisted => 'info',
            self::Cancelled => 'danger',
            self::Expired => 'gray',
        };
    }

    /**
     * @return list<self>
     */
    public static function permanentlyOccupying(): array
    {
        return [self::Confirmed, self::AwaitingApproval];
    }

    /**
     * @return list<self>
     */
    public static function temporarilyOccupying(): array
    {
        return [self::Held, self::Pending, self::Offered];
    }
}
