<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RegistrationStatus: string implements HasColor, HasLabel
{
    /** In a cart, seats are held for a few minutes. */
    case Held = 'held';
    /** Checked out, waiting for the double opt-in. */
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
    /** Siblings exceed the free seats; the course lead decides. */
    case AwaitingApproval = 'awaiting_approval';
    /** School class reservation waiting for manual approval. */
    case Requested = 'requested';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Held => 'Im Warenkorb',
            self::PendingConfirmation => 'Wartet auf Bestätigung',
            self::Confirmed => 'Bestätigt',
            self::AwaitingApproval => 'Wartet auf Freigabe',
            self::Requested => 'Angefragt',
            self::Declined => 'Abgelehnt',
            self::Cancelled => 'Storniert',
            self::Expired => 'Verfallen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Held, self::PendingConfirmation, self::Requested, self::AwaitingApproval => 'warning',
            self::Confirmed => 'success',
            self::Declined, self::Cancelled => 'danger',
            self::Expired => 'gray',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Held, self::PendingConfirmation, self::Confirmed, self::Requested, self::AwaitingApproval], true);
    }
}
