<?php

namespace App\Filament\Resources\Messages\Actions;

use App\Models\Message;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Marks a received message as read for the current user only.
 */
class MarkMessageAsReadAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'markAsRead';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Als gelesen markieren')
            ->icon(Heroicon::OutlinedEnvelopeOpen)
            ->color('gray')
            ->visible(fn (Message $record): bool => $record->isReceivedBy(filament()->auth()->user()) && ! $record->isReadBy(filament()->auth()->user()))
            ->action(fn (Message $record) => $record->markAsReadBy(filament()->auth()->user()));
    }
}
