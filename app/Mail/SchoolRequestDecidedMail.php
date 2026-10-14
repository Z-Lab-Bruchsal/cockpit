<?php

namespace App\Mail;

use App\Mail\Concerns\SendsAsHomepage;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SchoolRequestDecidedMail extends Mailable
{
    use Queueable, SendsAsHomepage, SerializesModels;

    public function __construct(
        public Registration $registration,
        public bool $approved,
        public ?string $visitUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->homepageSender(),
            subject: ($this->approved ? 'Termin bestätigt: ' : 'Terminanfrage abgelehnt: ').$this->registration->courseInstance->displayTitle(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.school-request-decided');
    }
}
