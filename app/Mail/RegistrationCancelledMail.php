<?php

namespace App\Mail;

use App\Mail\Concerns\SendsAsHomepage;
use App\Models\Enrollment;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationCancelledMail extends Mailable
{
    use Queueable, SendsAsHomepage, SerializesModels;

    /**
     * @param  Enrollment|null  $enrollment  the single cancelled seat, or null if the whole registration was cancelled
     */
    public function __construct(
        public Registration $registration,
        public ?Enrollment $enrollment = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->homepageSender(),
            subject: 'Abmeldung bestätigt: '.$this->registration->courseInstance->displayTitle(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.registration-cancelled');
    }
}
