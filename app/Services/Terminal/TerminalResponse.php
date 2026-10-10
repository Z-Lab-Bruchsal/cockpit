<?php

namespace App\Services\Terminal;

use App\Enums\TerminalEventResult;

/**
 * What the terminal shows on its display. Status is one of "ok", "warning", "error".
 */
readonly class TerminalResponse
{
    public function __construct(
        public string $status,
        public string $subject,
        public string $text,
        public TerminalEventResult $result,
    ) {}

    /**
     * @return array{status: string, subject: string, text: string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'subject' => $this->subject,
            'text' => $this->text,
        ];
    }
}
