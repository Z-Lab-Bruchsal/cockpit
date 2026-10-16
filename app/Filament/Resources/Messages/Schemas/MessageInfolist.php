<?php

namespace App\Filament\Resources\Messages\Schemas;

use App\Models\Message;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('sender.name')
                            ->label('Von'),
                        TextEntry::make('recipients')
                            ->label('An')
                            ->state(fn (Message $record): string => $record->recipientNames()),
                        TextEntry::make('created_at')
                            ->label('Gesendet')
                            ->dateTime(),
                        TextEntry::make('subject')
                            ->label('Betreff')
                            ->weight('bold')
                            ->columnSpanFull(),
                        TextEntry::make('body')
                            ->label('Nachricht')
                            ->html()
                            ->placeholder('Kein Text')
                            ->columnSpanFull(),
                    ]),
                Section::make('Gelesen')
                    ->description('Nur für dich als Absender sichtbar.')
                    ->columnSpanFull()
                    ->visible(fn (Message $record): bool => $record->user_id === filament()->auth()->id())
                    ->components([
                        TextEntry::make('receivers')
                            ->hiddenLabel()
                            ->state(fn (Message $record): array => $record->receivers()->orderBy('name')->get()
                                ->map(fn (User $receiver): string => $receiver->pivot->read_at
                                    ? "✓ {$receiver->name} (gelesen {$receiver->pivot->read_at->format('d.m.Y H:i')})"
                                    : "○ {$receiver->name} (ungelesen)")
                                ->all())
                            ->listWithLineBreaks(),
                    ]),
            ]);
    }
}
