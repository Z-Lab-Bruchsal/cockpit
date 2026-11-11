<?php

namespace App\Filament\Resources\Sections\RelationManagers;

use App\Enums\TileType;
use App\Filament\Resources\Sections\Schemas\TileForm;
use App\Models\Section;
use App\Models\Tile;
use App\Services\Homepage\LinkTarget;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The content of a section page: its tiles in grid order.
 */
class TilesRelationManager extends RelationManager
{
    protected static string $relationship = 'tiles';

    protected static ?string $title = 'Kacheln (Inhalt der Seite)';

    protected static ?string $modelLabel = 'Kachel';

    protected static ?string $pluralModelLabel = 'Kacheln';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $record = $schema->getRecord();

        return $schema
            ->components($record instanceof Tile && $record->type !== null ? TileForm::components($record->type) : [])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Tile $record): string => $record->label())
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('linkSection'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->paginated(false)
            ->description('Die Kacheln füllen die Seite in dieser Reihenfolge im Raster aus. Fein anordnen, Größen ziehen und neue Kacheln anlegen geht auch direkt auf der Homepage im Bearbeiten-Modus.')
            ->columns([
                TextColumn::make('type')
                    ->label('Art')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('label')
                    ->label('Kachel')
                    ->state(fn (Tile $record): string => $record->label())
                    ->wrap(),
                TextColumn::make('size')
                    ->label('Größe')
                    ->state(fn (Tile $record): string => $record->type === TileType::Courses
                        ? 'je Kurs '.(Tile::WIDTHS[(int) $record->setting('item_width', 2)] ?? '')
                        : trim((Tile::WIDTHS[$record->width] ?? '').($record->height === 2 ? ', doppelt hoch' : ''))),
                ToggleColumn::make('is_visible')
                    ->label('Sichtbar')
                    ->disabled(fn (Model $record): bool => ! auth()->user()?->can('update', $record)),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Art')
                    ->options(TileType::class),
            ])
            ->headerActions([
                Action::make('editOnHomepage')
                    ->label('Auf der Homepage bearbeiten')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->url(fn (): string => $this->getOwnerRecord()->url().'?bearbeiten=1')
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => $this->ownerSection()->isPubliclyVisible()),
                ActionGroup::make(array_map(fn (TileType $type): CreateAction => static::createActionFor($type), TileType::cases()))
                    ->label('Kachel hinzufügen')
                    ->icon(Heroicon::OutlinedPlus)
                    ->button(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(fn (Tile $record): string => $record->type?->getLabel().' bearbeiten'),
                Action::make('duplicate')
                    ->label('Duplizieren')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray')
                    ->authorize('replicate')
                    ->action(fn (Tile $record) => $record->duplicate())
                    ->successNotificationTitle('Kachel dupliziert'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function createActionFor(TileType $type): CreateAction
    {
        return CreateAction::make('create_'.$type->value)
            ->label($type->getLabel())
            ->icon($type->getIcon())
            ->modalHeading($type->getLabel().' hinzufügen')
            ->modalDescription($type->description())
            ->schema(TileForm::components($type))
            ->mutateDataUsing(fn (array $data): array => static::withType($data, $type));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withType(array $data, TileType $type): array
    {
        $data['type'] = $type->value;

        if ($type === TileType::Section) {
            $data['link_type'] = LinkTarget::SECTION;
        }

        if ($type === TileType::Courses) {
            $data['width'] ??= $type->defaultWidth();
        }

        return $data;
    }

    protected function ownerSection(): Section
    {
        /** @var Section $section */
        $section = $this->getOwnerRecord();

        return $section;
    }
}
