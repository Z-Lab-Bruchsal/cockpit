<?php

namespace App\Console\Commands;

use App\Mail\WorkTimeComplianceMail;
use App\Models\User;
use App\Services\WorkTime\WorkTimeCalculator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

#[Signature('app:send-break-compliance-warnings')]
#[Description('Send an email to users whose break time today does not satisfy the configured German labor-law thresholds')]
class SendBreakComplianceWarnings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WorkTimeCalculator $calculator): void
    {
        $today = today();

        $users = User::query()
            ->whereHas('timeEntries', fn (Builder $query) => $query->whereDate('happened_at', $today))
            ->get();

        foreach ($users as $user) {
            $warnings = $calculator->complianceWarnings($user, $today);

            if ($warnings === []) {
                continue;
            }

            Mail::to($user)->send(new WorkTimeComplianceMail($user, $today, $warnings));
        }
    }
}
