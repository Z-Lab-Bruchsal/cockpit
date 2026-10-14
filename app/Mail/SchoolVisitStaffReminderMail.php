<?php

namespace App\Mail;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Reminder for the course lead with everything about the upcoming school visit.
 */
class SchoolVisitStaffReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->registration->email ? [$this->registration->email] : [],
            subject: 'Erinnerung Schulklasse: '.$this->registration->school?->name.' – '.$this->registration->courseInstance->dates->first()?->starts_at->format('d.m.Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.school-visit-staff-reminder',
            with: [
                'cockpitUrl' => RegistrationResource::getUrl('view', ['record' => $this->registration], panel: 'admin'),
            ],
        );
    }
}
