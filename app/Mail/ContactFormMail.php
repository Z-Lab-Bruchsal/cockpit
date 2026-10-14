<?php

namespace App\Mail;

use App\Enums\ContactFormTopic;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Message from the contact form on the homepage, sent to the Z-LAB team.
 */
class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public ?string $senderPhone,
        public ContactFormTopic $topic,
        public string $body,
        public ?string $oldEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: 'Kontaktformular: '.$this->topic->getLabel(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contact-form',
        );
    }
}
