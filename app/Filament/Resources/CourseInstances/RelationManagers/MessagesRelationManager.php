<?php

namespace App\Filament\Resources\CourseInstances\RelationManagers;

use App\Models\CourseInstanceMessage;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * History of the mails sent to the participants of a course instance.
 */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'participantMessages';

    protected static ?string $title = 'Nachrichten';

    protected static ?string $modelLabel = 'Nachricht';

    protected static ?string $pluralModelLabel = 'Nachrichten';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->participantMessages()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('sender.name')
                    ->label('Von')
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->label('Gesendet')
                    ->dateTime(),
                TextEntry::make('subject')
                    ->label('Betreff')
                    ->weight('bold')
                    ->columnSpanFull(),
                TextEntry::make('body')
                    ->label('Nachricht')
                    ->state(fn (CourseInstanceMessage $record): string => nl2br(e($record->body)))
                    ->html()
                    ->columnSpanFull(),
                TextEntry::make('recipient_labels')
                    ->label('Empfänger')
                    ->state(fn (CourseInstanceMessage $record): array => $record->recipientLabels())
                    ->listWithLineBreaks()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('sender'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('subject')
                    ->label('Betreff')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('sender.name')
                    ->label('Von')
                    ->placeholder('—'),
                TextColumn::make('recipient_count')
                    ->label('Empfänger')
                    ->state(fn (CourseInstanceMessage $record): int => count($record->recipients ?? [])),
                TextColumn::make('created_at')
                    ->label('Gesendet')
                    ->dateTime(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
