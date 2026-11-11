<?php

namespace App\Services\Messages;

use App\Models\CompanySetting;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\Message as ImapMessage;

/**
 * Imports mails sent to the cockpit mailbox as internal messages for the users and groups set in the settings.
 *
 * Fetching (IMAP) and importing are separate, so the import rules can be tested without a mail server.
 * Mails from the cockpit's own sender address, auto replies and already imported mails are skipped.
 * Only the text is taken over; attachments stay in the mailbox.
 */
class InboundMailImporter
{
    /**
     * Precedence header values of automatic mails.
     */
    protected const array AUTOMATIC_PRECEDENCE = ['bulk', 'junk', 'auto_reply'];

    public function isEnabled(): bool
    {
        return (bool) config('imap.inbound.enabled');
    }

    /**
     * Fetches the unseen mails of the cockpit mailbox and imports them.
     * Without configured recipients nothing is fetched, so the mails stay unseen for a later import.
     *
     * @return int number of imported messages
     */
    public function import(): int
    {
        $recipientKeys = CompanySetting::current()->inbound_mail_recipients ?? [];

        if ($recipientKeys === []) {
            Log::warning('Eingehende E-Mails: keine Empfänger eingestellt, nichts importiert.');

            return 0;
        }

        $client = Client::account();
        $client->connect();

        $folder = $client->getFolderByPath(config('imap.inbound.folder'));
        $processedFolder = config('imap.inbound.processed_folder');

        $imported = 0;

        $mails = $folder->query()->whereUnseen()->setFetchOrderAsc()->limit(config('imap.inbound.limit'))->get();

        foreach ($mails as $mail) {
            try {
                if ($this->importMessage($this->extract($mail), $recipientKeys) instanceof Message) {
                    $imported++;
                }

                $mail->setFlag('Seen');

                if (filled($processedFolder)) {
                    $mail->move($processedFolder);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $client->disconnect();

        return $imported;
    }

    /**
     * Creates the internal message for one mail and delivers it to the recipients.
     *
     * @param  array{message_id: ?string, from_email: ?string, from_name: ?string, subject: ?string, text: ?string, html: ?string, attachment_count: int, auto_submitted: ?string, precedence: ?string}  $mail
     * @param  array<int, string>  $recipientKeys
     * @return Message|null null if the mail was skipped
     */
    public function importMessage(array $mail, array $recipientKeys): ?Message
    {
        $fromEmail = Str::lower(trim((string) $mail['from_email']));

        if ($fromEmail === '' || $recipientKeys === [] || $this->isOwnAddress($fromEmail) || $this->isAutomatic($mail)) {
            return null;
        }

        $messageId = filled($mail['message_id'])
            ? Str::limit(trim($mail['message_id'], " <>\t\n\r"), 250, '')
            : 'sha1:'.sha1($fromEmail.'|'.$mail['subject'].'|'.$mail['text'].'|'.$mail['html']);

        if (Message::query()->where('external_message_id', $messageId)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($mail, $fromEmail, $messageId, $recipientKeys): Message {
            $message = Message::query()->create([
                'user_id' => null,
                'sender_email' => $fromEmail,
                'sender_name' => filled($mail['from_name']) ? Str::limit(trim($mail['from_name']), 250, '') : null,
                'external_message_id' => $messageId,
                'subject' => filled($mail['subject']) ? Str::limit(trim($mail['subject']), 250) : '(kein Betreff)',
                'body' => $this->body($mail),
            ]);

            $message->syncRecipients($recipientKeys);

            return $message;
        });
    }

    /**
     * Safe HTML for the message: the text part (or the HTML part as plain text) escaped, plus a note about attachments.
     *
     * @param  array{text: ?string, html: ?string, attachment_count: int}  $mail
     */
    protected function body(array $mail): string
    {
        $text = filled($mail['text']) ? (string) $mail['text'] : $this->htmlToText((string) $mail['html']);
        $text = Str::limit(trim(preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", $text))), 50000);

        $body = $text === '' ? '' : '<p>'.nl2br(e($text), false).'</p>';

        if ($mail['attachment_count'] > 0) {
            $body .= '<p><em>'.($mail['attachment_count'] === 1 ? '1 Anhang wurde' : "{$mail['attachment_count']} Anhänge wurden").' nicht übernommen – er liegt im Postfach.</em></p>';
        }

        return $body;
    }

    protected function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<br\s*/?>|</(p|div|li|tr|h[1-6])>#i', "\n", $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function isOwnAddress(string $email): bool
    {
        return $email === Str::lower((string) config('mail.from.address'));
    }

    /**
     * @param  array{auto_submitted: ?string, precedence: ?string}  $mail
     */
    protected function isAutomatic(array $mail): bool
    {
        $autoSubmitted = Str::lower(trim((string) $mail['auto_submitted']));

        return ($autoSubmitted !== '' && $autoSubmitted !== 'no')
            || in_array(Str::lower(trim((string) $mail['precedence'])), self::AUTOMATIC_PRECEDENCE, true);
    }

    /**
     * @return array{message_id: ?string, from_email: ?string, from_name: ?string, subject: ?string, text: ?string, html: ?string, attachment_count: int, auto_submitted: ?string, precedence: ?string}
     */
    protected function extract(ImapMessage $mail): array
    {
        $header = $mail->getHeader();
        $from = $mail->getFrom()->first();

        return [
            'message_id' => $this->headerValue($header, 'message_id'),
            'from_email' => $from instanceof Address ? $from->mail : null,
            'from_name' => $from instanceof Address ? $from->personal : null,
            'subject' => $this->headerValue($header, 'subject'),
            'text' => $mail->hasTextBody() ? $mail->getTextBody() : null,
            'html' => $mail->hasHTMLBody() ? $mail->getHTMLBody() : null,
            'attachment_count' => $mail->getAttachments()->count(),
            'auto_submitted' => $this->headerValue($header, 'auto_submitted'),
            'precedence' => $this->headerValue($header, 'precedence'),
        ];
    }

    protected function headerValue(?Header $header, string $name): ?string
    {
        if (! $header?->has($name)) {
            return null;
        }

        $value = $header->get($name)->first();

        return is_scalar($value) ? (string) $value : null;
    }
}
