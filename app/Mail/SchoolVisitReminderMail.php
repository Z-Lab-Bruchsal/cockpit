<?php

namespace App\Mail;

use App\Enums\SchoolVenue;
use App\Mail\Concerns\SendsAsHomepage;
use App\Models\CompanySetting;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Reminder for the teacher before the visit: check the details and confirm that it takes place.
 * Visits in the Z-LAB get the directions sheet from the company settings attached.
 */
class SchoolVisitReminderMail extends Mailable
{
    use Queueable, SendsAsHomepage, SerializesModels;

    public function __construct(
        public Registration $registration,
        public string $visitUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->homepageSender(),
            subject: 'Erinnerung: '.$this->registration->courseInstance->displayTitle().' am '.$this->registration->courseInstance->dates->first()?->starts_at->format('d.m.Y'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.school-visit-reminder');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = CompanySetting::current()->directions_document_path;

        if ($this->registration->venue !== SchoolVenue::ZLab || blank($path) || ! Storage::disk('local')->exists($path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $path)->as('Anfahrt.'.pathinfo($path, PATHINFO_EXTENSION)),
        ];
    }
}
