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
 * Tells the course lead what a school did with its booking: confirmed the visit (maybe with corrections) or cancelled it.
 */
class SchoolBookingUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';

    /**
     * @param  list<string>  $changes
     */
    public function __construct(
        public Registration $registration,
        public string $kind,
        public array $changes = [],
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->kind === self::CANCELLED ? 'Schule hat storniert: ' : 'Schule hat bestätigt: ';

        return new Envelope(
            replyTo: $this->registration->email ? [$this->registration->email] : [],
            subject: $prefix.$this->registration->school?->name.' – '.$this->registration->courseInstance->displayTitle(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.school-booking-update',
            with: [
                'cockpitUrl' => RegistrationResource::getUrl('view', ['record' => $this->registration], panel: 'admin'),
            ],
        );
    }
}
