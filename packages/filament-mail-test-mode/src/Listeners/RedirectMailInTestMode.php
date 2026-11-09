<?php

namespace AlexanderGabriel\FilamentMailTestMode\Listeners;

use AlexanderGabriel\FilamentMailTestMode\MailTestMode;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;

/**
 * Rewrites every outgoing mail to the test recipient while the test mode is active.
 */
class RedirectMailInTestMode
{
    public function __construct(protected MailTestMode $mailTestMode) {}

    public function handle(MessageSending $event): ?bool
    {
        if (! $this->mailTestMode->isEnabled()) {
            return null;
        }

        $message = $event->message;
        $recipient = $this->mailTestMode->recipient();

        if ($recipient === null) {
            Log::warning('Mail test mode is active without a recipient, mail was discarded.', [
                'subject' => $message->getSubject(),
                'to' => $this->formatAddresses($message->getTo()),
            ]);

            return false;
        }

        $headers = $message->getHeaders();

        foreach (['To' => $message->getTo(), 'Cc' => $message->getCc(), 'Bcc' => $message->getBcc()] as $field => $addresses) {
            if ($addresses !== []) {
                $headers->addTextHeader("X-Original-{$field}", $this->formatAddresses($addresses));
            }
        }

        $message->to($recipient);
        $message->cc();
        $message->bcc();

        $prefix = $this->mailTestMode->subjectPrefix();

        if ($prefix !== '' && ! Str::startsWith((string) $message->getSubject(), $prefix)) {
            $message->subject($prefix.$message->getSubject());
        }

        return null;
    }

    /**
     * @param  array<int, Address>  $addresses
     */
    protected function formatAddresses(array $addresses): string
    {
        return implode(', ', array_map(fn (Address $address): string => $address->toString(), $addresses));
    }
}
