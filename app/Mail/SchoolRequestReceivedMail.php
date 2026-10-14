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
 * Receipt for the teacher: the slots are reserved, the course lead still has to approve them.
 */
class SchoolRequestReceivedMail extends Mailable
{
    use Queueable, SendsAsHomepage, SerializesModels;

    /**
     * @param  Collection<int, Registration>  $registrations
     */
    public function __construct(public Collection $registrations) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->homepageSender(),
            subject: 'Ihre Terminanfrage ist eingegangen',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.school-request-received');
    }
}
