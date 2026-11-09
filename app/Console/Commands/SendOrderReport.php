<?php

namespace App\Console\Commands;

use App\Mail\OrderReportMail;
use App\Models\CompanySetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('app:send-order-report {--force : Sofort senden, auch wenn der Bericht nicht fällig ist}')]
#[Description('Verschickt den wöchentlichen Bestellbericht (Excel + PDF) an die eingestellten Empfänger')]
class SendOrderReport extends Command
{
    public function handle(): int
    {
        $settings = CompanySetting::current();
        $recipients = array_values(array_filter((array) $settings->order_report_recipients, fn (mixed $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));

        if (! $this->option('force') && ! $this->isDue($settings)) {
            $this->info('Bestellbericht ist nicht fällig.');

            return self::SUCCESS;
        }

        if ($recipients === []) {
            $this->warn('Keine Empfänger für den Bestellbericht eingetragen.');

            return self::SUCCESS;
        }

        $to = now();
        $from = $settings->order_report_last_sent_at ?? $to->copy()->subWeek();

        Mail::to($recipients)->send(new OrderReportMail($from, $to));

        $settings->update(['order_report_last_sent_at' => $to]);

        $this->info('Bestellbericht an '.implode(', ', $recipients).' verschickt.');

        return self::SUCCESS;
    }

    /**
     * Due on the configured weekday from the configured time on, once per day.
     */
    protected function isDue(CompanySetting $settings): bool
    {
        if (! $settings->order_report_enabled || now()->dayOfWeekIso !== $settings->order_report_weekday) {
            return false;
        }

        if (now()->format('H:i') < ($settings->order_report_time ?: '07:00')) {
            return false;
        }

        return $settings->order_report_last_sent_at === null || $settings->order_report_last_sent_at->lt(today());
    }
}
