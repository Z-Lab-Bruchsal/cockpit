<?php

namespace App\Mail;

use App\Mail\Concerns\SendsAsHomepage;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RegistrationConfirmedMail extends Mailable
{
    use Queueable, SendsAsHomepage, SerializesModels;

    /**
     * @param  Collection<int, Registration>  $registrations
     * @param  array<int, string>  $manageUrls  keyed by registration id
     */
    public function __construct(
        public Collection $registrations,
        public array $manageUrls,
        public bool $siblingsApproved = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->homepageSender(),
            subject: $this->siblingsApproved ? 'Alle Geschwister dürfen teilnehmen' : 'Deine Kursanmeldung ist bestätigt',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.registration-confirmed');
    }
}
