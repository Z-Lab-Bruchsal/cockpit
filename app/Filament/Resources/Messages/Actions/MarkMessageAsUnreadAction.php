<?php

namespace App\Filament\Resources\Messages\Actions;

use App\Models\Message;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Marks a received message as unread again for the current user only.
 */
class MarkMessageAsUnreadAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'markAsUnread';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Als ungelesen markieren')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (Message $record): bool => $record->isReadBy(filament()->auth()->user()))
            ->action(fn (Message $record) => $record->markAsUnreadBy(filament()->auth()->user()));
    }
}
