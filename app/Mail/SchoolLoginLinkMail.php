<?php

namespace App\Mail;

use App\Mail\Concerns\SendsAsHomepage;
use App\Models\Contact;
use App\Services\Contacts\MagicLinkService;
use App\Services\Schools\SchoolAccountService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation link for a newly registered school, or invitation of a teacher by the school account.
 */
class SchoolLoginLinkMail extends Mailable
{
    use Queueable, SendsAsHomepage, SerializesModels;

    public const REGISTRATION = 'registration';

    public const INVITATION = 'invitation';

    public function __construct(
        public string $kind,
        public string $url,
        public Contact $contact,
        public ?Contact $school = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->homepageSender(),
            subject: $this->kind === self::INVITATION
                ? 'Einladung: Schulkonto '.$this->school?->name
                : 'Schulkonto bestätigen',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.school-login-link',
            with: [
                'validity' => $this->kind === self::INVITATION
                    ? (SchoolAccountService::INVITATION_LIFETIME_MINUTES / 1440).' Tage'
                    : MagicLinkService::LIFETIME_MINUTES.' Minuten',
            ],
        );
    }
}
