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

/**
 * Double opt-in after checkout: one link confirms all registrations of the checkout.
 */
class RegistrationConfirmationMail extends Mailable
{
    use Queueable, SendsAsHomepage, SerializesModels;

    /**
     * @param  Collection<int, Registration>  $registrations
     */
    public function __construct(
        public Collection $registrations,
        public string $confirmationUrl,
        public string $name,
        public int $confirmationHours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->homepageSender(),
            subject: 'Bitte bestätige deine Kursanmeldung',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.registration-confirmation');
    }
}
