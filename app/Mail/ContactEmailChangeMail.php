<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Models\ContactEmailChange;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * All mails around a changed email address: confirmation by the old address, confirmation by the new address,
 * and the notice to the old address once the change has happened (also for changes made in the cockpit).
 */
class ContactEmailChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public const STEP_CONFIRM_OLD = 'confirm_old';

    public const STEP_CONFIRM_NEW = 'confirm_new';

    public const STEP_CHANGED = 'changed';

    public function __construct(
        public string $step,
        public string $newEmail,
        public ?string $url = null,
        public ?string $name = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), CompanySetting::current()->homepageTitle()),
            subject: match ($this->step) {
                self::STEP_CONFIRM_OLD => 'Änderung deiner E-Mail-Adresse bestätigen',
                self::STEP_CONFIRM_NEW => 'Neue E-Mail-Adresse bestätigen',
                default => 'Deine E-Mail-Adresse wurde geändert',
            },
        );
    }

    public function content(): Content
    {
        $settings = CompanySetting::current();

        return new Content(
            markdown: 'mail.contact-email-change',
            with: [
                'lifetimeHours' => ContactEmailChange::LIFETIME_HOURS,
                'contactEmail' => $settings->contact_email,
                'contactPhone' => $settings->contact_phone,
                'signature' => $settings->homepageTitle(),
            ],
        );
    }
}
