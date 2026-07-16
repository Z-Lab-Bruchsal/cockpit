<?php

namespace App\Mail;

use App\Filament\Resources\Todos\TodoResource;
use App\Models\Todo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TodoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Todo $todo,
        public string $mail_subject,
        public string $message,
        public string $notiz = '',
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mail_subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.todo-mail',
            with: [
                'message' => $this->message,
                'subject' => $this->mail_subject,
                'notiz' => $this->notiz,
                'todo_url' => TodoResource::getUrl('edit', ['record' => $this->todo]),
                'todo' => $this->todo,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
