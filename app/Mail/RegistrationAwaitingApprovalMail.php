<?php

namespace App\Mail;

use App\Mail\Concerns\SendsAsHomepage;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationAwaitingApprovalMail extends Mailable
{
    use Queueable, SendsAsHomepage, SerializesModels;

    public function __construct(public Registration $registration) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->homepageSender(),
            subject: 'Deine Anmeldung ist eingegangen: '.$this->registration->courseInstance->displayTitle(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.registration-awaiting-approval');
    }
}
