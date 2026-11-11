<?php

namespace App\Filament\Pages\Inline;

use App\Filament\Resources\Sections\Schemas\TileForm;
use App\Models\Tile;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/**
 * Edits a single tile of a section directly from the homepage.
 */
class EditTileInline extends InlineEditPage
{
    protected static ?string $slug = 'homepage/tile';

    #[Locked]
    public string $tileUuid = '';

    #[Locked]
    public string $tileTypeLabel = '';

    public function mount(): void
    {
        $this->tileUuid = (string) request()->query('tile', '');

        $tile = $this->tile();

        abort_if($tile->type === null, 404);

        $this->tileTypeLabel = $tile->type->getLabel();

        $this->form->fill($tile->attributesToArray());
    }

    /**
     * Must not query anything: Shield reads the titles of all pages, e.g. when editing roles.
     */
    public function getTitle(): string
    {
        return ($this->tileTypeLabel ?: 'Kachel').' bearbeiten';
    }

    public function form(Schema $schema): Schema
    {
        $tile = $this->tile();

        return $schema
            ->components([
                Callout::make('Seite „'.$tile->section->title.'“')
                    ->description('Änderungen sind nach dem Speichern sofort auf der Homepage sichtbar.')
                    ->info(),
                ...TileForm::components($tile->type),
            ])
            ->columns(2)
            ->model($tile)
            ->statePath('data');
    }

    protected function persist(array $data): void
    {
        $this->tile()->update($data);
    }

    protected function tile(): Tile
    {
        $tile = Tile::query()->with('section')->where('uuid', $this->tileUuid)->firstOrFail();

        Gate::authorize('update', $tile);

        return $tile;
    }
}
