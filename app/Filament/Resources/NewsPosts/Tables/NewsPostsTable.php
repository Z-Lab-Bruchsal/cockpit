<?php

namespace App\Filament\Resources\NewsPosts\Tables;

use App\Models\NewsPost;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Bild')
                    ->disk('public')
                    ->square()
                    ->imageSize(40),
                TextColumn::make('title')
                    ->label('Titel')
                    ->description(fn (NewsPost $record): ?string => $record->teaser ? str($record->teaser)->limit(80)->toString() : null)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (NewsPost $record): string => static::status($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Veröffentlicht' => 'success',
                        'Geplant' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('published_at')
                    ->label('Veröffentlicht am')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Bearbeitet')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->placeholder('Alle')
                    ->options([
                        'published' => 'Veröffentlicht',
                        'scheduled' => 'Geplant',
                        'draft' => 'Entwurf',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'published' => $query->where('published_at', '<=', now()),
                        'scheduled' => $query->where('published_at', '>', now()),
                        'draft' => $query->whereNull('published_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('viewOnHomepage')
                    ->label('Auf der Homepage ansehen')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->url(fn (NewsPost $record): string => $record->url())
                    ->openUrlInNewTab()
                    ->visible(fn (NewsPost $record): bool => $record->isPublished()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function status(NewsPost $record): string
    {
        return match (true) {
            $record->published_at === null => 'Entwurf',
            $record->published_at->isFuture() => 'Geplant',
            default => 'Veröffentlicht',
        };
    }
}
