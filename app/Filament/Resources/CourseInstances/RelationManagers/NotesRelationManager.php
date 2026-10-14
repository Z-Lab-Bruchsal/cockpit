<?php

namespace App\Filament\Resources\CourseInstances\RelationManagers;

use App\Models\CourseInstanceNote;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Internal notes on a course instance, e.g. from calls with the school about directions or special needs.
 */
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'internalNotes';

    protected static ?string $title = 'Notizen';

    protected static ?string $modelLabel = 'Notiz';

    protected static ?string $pluralModelLabel = 'Notizen';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->internalNotes()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('Notiz')
                    ->rows(5)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'registration.school']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('body')
                    ->label('Notiz')
                    ->wrap(),
                TextColumn::make('registration.school.name')
                    ->label('Schule')
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('Von')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Am')
                    ->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Notiz hinzufügen')
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (CourseInstanceNote $record): bool => $record->user_id === auth()->id()),
                DeleteAction::make()
                    ->visible(fn (CourseInstanceNote $record): bool => $record->user_id === auth()->id()),
            ]);
    }
}
