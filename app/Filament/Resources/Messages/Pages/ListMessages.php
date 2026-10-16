<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Neue Nachricht'),
        ];
    }

    public function getTabs(): array
    {
        $user = filament()->auth()->user();
        $unreadCount = Message::query()->unreadBy($user)->count();

        return [
            'inbox' => Tab::make('Posteingang')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->receivedBy($user)),
            'unread' => Tab::make('Ungelesen')
                ->badge($unreadCount > 0 ? $unreadCount : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->unreadBy($user)),
            'sent' => Tab::make('Gesendet')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->sentBy($user)),
        ];
    }
}
