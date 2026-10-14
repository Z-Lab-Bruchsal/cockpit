<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Services\Contacts\MagicLinkService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $url,
        public bool $isNewContact,
        public ?string $name = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), CompanySetting::current()->homepageTitle()),
            subject: $this->isNewContact ? 'Registrierung bestätigen' : 'Dein Anmeldelink',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contact-magic-link',
            with: [
                'lifetimeMinutes' => MagicLinkService::LIFETIME_MINUTES,
            ],
        );
    }
}
