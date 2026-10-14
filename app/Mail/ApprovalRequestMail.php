<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Asks the course lead to approve or decline: siblings beyond the free seats,
 * or a waitlist family bigger than the freed seats.
 */
class ApprovalRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Registration $registration,
        public string $approveUrl,
        public string $declineUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->registration->email ? [$this->registration->email] : [],
            subject: 'Freigabe nötig: '.$this->registration->courseInstance->displayTitle(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.approval-request');
    }
}
