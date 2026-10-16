<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\Actions\MarkMessageAsReadAction;
use App\Filament\Resources\Messages\Actions\MarkMessageAsUnreadAction;
use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMessage extends ViewRecord
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MarkMessageAsReadAction::make(),
            MarkMessageAsUnreadAction::make(),
            Action::make('reply')
                ->label('Antworten')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->visible(fn (Message $record): bool => $record->user_id !== filament()->auth()->id() && MessageResource::canCreate())
                ->url(fn (Message $record): string => MessageResource::getUrl('create', ['reply' => $record->id])),
            DeleteAction::make(),
        ];
    }
}
