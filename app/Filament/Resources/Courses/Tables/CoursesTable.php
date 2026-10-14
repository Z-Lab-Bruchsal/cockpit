<?php

namespace App\Filament\Resources\Courses\Tables;

use App\Enums\CourseAudience;
use App\Models\Course;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Bearbeitet')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('card_image_path')
                    ->label('Bild')
                    ->disk('public')
                    ->square()
                    ->imageSize(40),
                TextColumn::make('name')
                    ->label('Name')
                    ->description(fn (Course $record): ?string => $record->ageRangeLabel())
                    ->searchable()
                    ->sortable(),
                TextColumn::make('audience')
                    ->label('Zielgruppe')
                    ->badge(),
                TextColumn::make('interests.name')
                    ->label('Interessen')
                    ->badge()
                    ->separator(','),
                TextColumn::make('responsibleUser.name')
                    ->label('Verantwortlich')
                    ->placeholder('fehlt')
                    ->color(fn (Course $record): ?string => $record->responsible_user_id === null ? 'danger' : null),
                TextColumn::make('upcoming_instances_count')
                    ->label('Kommende Durchführungen')
                    ->counts('upcomingInstances'),
                TextColumn::make('duration')
                    ->label('Dauer [min]')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_published')
                    ->label('Veröffentlicht'),
            ])
            ->filters([
                SelectFilter::make('audience')
                    ->label('Zielgruppe')
                    ->options(CourseAudience::class),
                SelectFilter::make('interests')
                    ->label('Interesse')
                    ->relationship('interests', 'name')
                    ->multiple()
                    ->preload(),
                TernaryFilter::make('is_published')
                    ->label('Veröffentlicht'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('viewOnHomepage')
                    ->label('Auf der Homepage ansehen')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->url(fn (Course $record): string => $record->url())
                    ->openUrlInNewTab()
                    ->visible(fn (Course $record): bool => $record->is_published),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
