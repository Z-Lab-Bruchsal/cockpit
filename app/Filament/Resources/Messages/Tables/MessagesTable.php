<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Filament\Resources\Messages\Actions\MarkMessageAsReadAction;
use App\Filament\Resources\Messages\Actions\MarkMessageAsUnreadAction;
use App\Models\Message;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('read_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Message $record): string => self::readStatus($record))
                    ->color(fn (string $state): string => match ($state) {
                        'ungelesen' => 'warning',
                        'gelesen' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('sender.name')
                    ->label('Von')
                    ->searchable(),
                TextColumn::make('recipients')
                    ->label('An')
                    ->state(fn (Message $record): string => $record->recipientNames())
                    ->limit(50)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query) => $query
                        ->whereHas('recipientUsers', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('recipientGroups', fn (Builder $groups) => $groups->where('name', 'like', "%{$search}%")))),
                TextColumn::make('subject')
                    ->label('Betreff')
                    ->searchable()
                    ->weight(fn (Message $record): ?FontWeight => self::readStatus($record) === 'ungelesen' ? FontWeight::Bold : null),
                TextColumn::make('created_at')
                    ->label('Gesendet')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                MarkMessageAsReadAction::make()->iconButton(),
                MarkMessageAsUnreadAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markAsRead')
                        ->label('Als gelesen markieren')
                        ->icon(Heroicon::OutlinedEnvelopeOpen)
                        ->action(fn (Collection $records) => $records->each(fn (Message $message) => $message->markAsReadBy(filament()->auth()->user())))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markAsUnread')
                        ->label('Als ungelesen markieren')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->action(fn (Collection $records) => $records->each(fn (Message $message) => $message->markAsUnreadBy(filament()->auth()->user())))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * "gelesen"/"ungelesen" for received messages, read progress like "2/3 gelesen" for sent ones.
     */
    private static function readStatus(Message $message): string
    {
        $user = filament()->auth()->user();

        if ($message->isReceivedBy($user)) {
            return $message->isReadBy($user) ? 'gelesen' : 'ungelesen';
        }

        return $message->readProgress().' gelesen';
    }
}
