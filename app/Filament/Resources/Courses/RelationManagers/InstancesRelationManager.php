<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Filament\Resources\CourseInstances\CourseInstanceResource;
use App\Models\CourseInstance;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InstancesRelationManager extends RelationManager
{
    protected static string $relationship = 'instances';

    protected static ?string $title = 'Durchführungen';

    protected static ?string $relatedResource = CourseInstanceResource::class;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['firstDate', 'lastDate'])->withCount('dates'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->placeholder('—'),
                TextColumn::make('firstDate.starts_at')
                    ->label('Beginn')
                    ->dateTime(),
                TextColumn::make('lastDate.ends_at')
                    ->label('Ende')
                    ->dateTime(),
                TextColumn::make('dates_count')
                    ->label('Termine'),
                TextColumn::make('capacity')
                    ->label('Plätze'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('availability')
                    ->label('Anmeldung')
                    ->state(fn (CourseInstance $record) => $record->availability())
                    ->badge(),
            ])
            ->headerActions([
                Action::make('createInstance')
                    ->label('Neue Durchführung')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn (): string => CourseInstanceResource::getUrl('create', ['course' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
