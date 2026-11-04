<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Message from a business card on the homepage, sent from the cockpit address to a team member.
 */
class TeamContactMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $senderName,
        public string $senderEmail,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: 'Nachricht über die Homepage von '.$this->senderName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.team-contact',
        );
    }
}
