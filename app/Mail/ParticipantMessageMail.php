<?php

namespace App\Mail;

use App\Mail\Concerns\SendsAsHomepage;
use App\Models\CourseInstanceMessage;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A message written in the cockpit to the participants of a course instance.
 */
class ParticipantMessageMail extends Mailable
{
    use Queueable, SendsAsHomepage, SerializesModels;

    public function __construct(
        public CourseInstanceMessage $participantMessage,
        public Registration $registration,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->homepageSender(),
            subject: $this->participantMessage->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.participant-message',
            with: [
                'instance' => $this->participantMessage->courseInstance,
                'contactName' => $this->registration->contact?->name ?? $this->registration->contact_name,
            ],
        );
    }
}
