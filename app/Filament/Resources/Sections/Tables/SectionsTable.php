<?php

namespace App\Filament\Resources\Sections\Tables;

use App\Models\Section;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('parent.parent'))
            ->defaultSort(fn (Builder $query): Builder => Section::orderByTree($query))
            ->reorderable('sort')
            ->afterReordering(function (): void {
                Section::renumberInTreeOrder();
            })
            ->columns([
                ImageColumn::make('tile_image_path')
                    ->label('Bild')
                    ->disk('public')
                    ->square()
                    ->imageSize(40),
                TextColumn::make('title')
                    ->label('Titel')
                    ->formatStateUsing(fn (Section $record, string $state): string => str_repeat('— ', $record->depth()).$state)
                    ->description(fn (Section $record): ?string => $record->is_home ? 'Startseite' : $record->parent?->title)
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Kurzname')
                    ->toggleable(),
                ToggleColumn::make('is_visible')
                    ->label('Sichtbar')
                    ->disabled(fn (Model $record): bool => ! auth()->user()?->can('update', $record)),
                IconColumn::make('show_in_navigation')
                    ->label('Kopfzeile')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Bearbeitet')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Übergeordneter Bereich')
                    ->placeholder('Alle')
                    ->options(fn (): array => ['root' => '— Nur Hauptbereiche —'] + Section::orderByTree(Section::query())->pluck('title', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            null, '' => $query,
                            'root' => $query->whereNull('parent_id'),
                            default => $query->where('parent_id', $data['value']),
                        };
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('viewOnHomepage')
                    ->label('Auf der Homepage ansehen')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->url(fn (Section $record): string => $record->url())
                    ->openUrlInNewTab()
                    ->visible(fn (Section $record): bool => $record->isPubliclyVisible()),
                Action::make('arrangeTiles')
                    ->label('Kacheln anordnen')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->url(fn (Section $record): string => $record->url().'?anordnen=1')
                    ->openUrlInNewTab()
                    ->visible(fn (Section $record): bool => $record->isPubliclyVisible()
                        && $record->hasTiles()
                        && (bool) auth()->user()?->can('reorder', Section::class)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
