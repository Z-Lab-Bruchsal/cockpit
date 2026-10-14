<?php

namespace App\Filament\Resources\CourseInstances\Tables;

use App\Enums\CourseInstanceStatus;
use App\Filament\Resources\CourseInstances\Actions\NotifyInterestedAction;
use App\Filament\Resources\CourseInstances\CourseInstanceResource;
use App\Models\CourseInstance;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CourseInstancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['course', 'firstDate', 'lastDate'])->withCount('dates'))
            ->columns([
                TextColumn::make('course.name')
                    ->label('Kurs')
                    ->description(fn (CourseInstance $record): ?string => $record->title)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('firstDate.starts_at')
                    ->label('Beginn')
                    ->dateTime(),
                TextColumn::make('lastDate.ends_at')
                    ->label('Ende')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dates_count')
                    ->label('Termine'),
                TextColumn::make('capacity')
                    ->label('Belegt')
                    ->state(fn (CourseInstance $record): string => $record->takenSeats().' / '.$record->capacity)
                    ->description(fn (CourseInstance $record): ?string => ($waiting = $record->waitlistCount()) > 0 ? "{$waiting} auf Warteliste" : null),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('availability')
                    ->label('Anmeldung')
                    ->state(fn (CourseInstance $record) => $record->availability())
                    ->badge(),
                TextColumn::make('notifications_count')
                    ->label('Benachrichtigt')
                    ->counts('notifications')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location')
                    ->label('Ort')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('course')
                    ->label('Kurs')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(CourseInstanceStatus::class),
                Filter::make('upcoming')
                    ->label('Nur kommende')
                    ->query(fn (Builder $query): Builder => $query->upcoming())
                    ->default(),
            ])
            ->recordActions([
                EditAction::make(),
                NotifyInterestedAction::make(),
                ReplicateAction::make()
                    ->label('Kopieren')
                    ->excludeAttributes(['dates_count'])
                    ->beforeReplicaSaved(fn (CourseInstance $replica) => $replica->status = CourseInstanceStatus::Draft)
                    ->successRedirectUrl(fn (CourseInstance $replica): string => CourseInstanceResource::getUrl('edit', ['record' => $replica])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
