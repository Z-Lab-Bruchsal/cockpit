<?php

namespace App\Enums;

use App\Models\CourseInstance;
use Filament\Support\Contracts\HasLabel;

/**
 * State of a school class slot as shown to schools. Never reveals which school booked it.
 */
enum SchoolSlotState: string implements HasLabel
{
    case Free = 'free';
    case InCart = 'in_cart';
    case Requested = 'requested';
    case Reserved = 'reserved';
    case Closed = 'closed';

    public static function for(CourseInstance $instance): self
    {
        $availability = $instance->availability();

        if (in_array($availability, [CourseAvailability::Cancelled, CourseAvailability::Closed, CourseAvailability::NotYetOpen], true)) {
            return self::Closed;
        }

        if ($instance->freeSeats() > 0) {
            return self::Free;
        }

        if ($instance->enrollments()->where('status', EnrollmentStatus::Confirmed)->exists()) {
            return self::Reserved;
        }

        if ($instance->enrollments()->where('status', EnrollmentStatus::Requested)->exists()) {
            return self::Requested;
        }

        return $instance->temporarilyHeldSeats() > 0 ? self::InCart : self::Reserved;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Free => 'Frei',
            self::InCart => 'Gerade im Warenkorb einer anderen Schule',
            self::Requested => 'Angefragt',
            self::Reserved => 'Vergeben',
            self::Closed => 'Nicht buchbar',
        };
    }

    public function hexColor(): string
    {
        return match ($this) {
            self::Free => '#16a34a',
            self::InCart => '#9333ea',
            self::Requested => '#d97706',
            self::Reserved => '#dc2626',
            self::Closed => '#6b7280',
        };
    }
}
