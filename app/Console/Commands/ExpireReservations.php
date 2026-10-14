<?php

namespace App\Console\Commands;

use App\Services\Registrations\CourseReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:expire-reservations')]
#[Description('Lässt abgelaufene Reservierungen, unbestätigte Anmeldungen und Wartelisten-Angebote verfallen und bietet frei gewordene Plätze der Warteliste an')]
class ExpireReservations extends Command
{
    public function handle(CourseReservationService $reservations): int
    {
        $expired = $reservations->expireOverdue();

        $this->info("{$expired} Plätze verfallen.");

        return self::SUCCESS;
    }
}
