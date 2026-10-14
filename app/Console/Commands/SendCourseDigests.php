<?php

namespace App\Console\Commands;

use App\Services\Notifications\InterestNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-course-digests')]
#[Description('Verschickt gesammelte Infos zu neuen Kursen an Eltern, sobald ihre Sammelzeit abgelaufen ist')]
class SendCourseDigests extends Command
{
    public function handle(InterestNotificationService $notifications): int
    {
        $sent = $notifications->sendDueDigests();

        $this->info("{$sent} Sammelmails verschickt.");

        return self::SUCCESS;
    }
}
