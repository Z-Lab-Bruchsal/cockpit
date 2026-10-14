<?php

namespace App\Console\Commands;

use App\Services\Schools\SchoolBookingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-school-reminders')]
#[Description('Erinnert Lehrkräfte und Kursverantwortliche vor gebuchten Schulklassen-Terminen')]
class SendSchoolReminders extends Command
{
    public function handle(SchoolBookingService $schoolBookings): int
    {
        $sent = $schoolBookings->sendDueReminders();

        $this->info("{$sent['teachers']} Erinnerungen an Lehrkräfte, {$sent['staff']} an Kursverantwortliche verschickt.");

        return self::SUCCESS;
    }
}
