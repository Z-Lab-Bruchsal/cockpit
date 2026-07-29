<?php

namespace App\Mail;

use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class WorkTimeComplianceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public User $user,
        public Carbon $date,
        public array $warnings,
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Hinweis zu deiner Pausenzeit',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.work-time-compliance-mail',
            with: [
                'user' => $this->user,
                'date' => $this->date,
                'warnings' => $this->warnings,
                'time_entries_url' => TimeEntryResource::getUrl('index'),
            ],
        );
    }
}
