<?php

namespace App\Filament\Pages\Inline;

use App\Enums\TileType;
use App\Filament\Resources\Sections\RelationManagers\TilesRelationManager;
use App\Filament\Resources\Sections\Schemas\TileForm;
use App\Models\Section;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/**
 * Adds a tile of a given type at the end of a section page, opened from the tile editor on the homepage.
 */
class CreateTileInline extends InlineEditPage
{
    protected static ?string $slug = 'homepage/tile/new';

    #[Locked]
    public int $sectionId;

    #[Locked]
    public string $tileType = '';

    public function mount(): void
    {
        $this->sectionId = request()->integer('section');
        $this->tileType = (string) request()->query('type', '');

        $this->section();
        abort_if(TileType::tryFrom($this->tileType) === null, 404);

        $this->form->fill();
    }

    /**
     * Must not query or abort: Shield reads the titles of all pages, e.g. when editing roles.
     */
    public function getTitle(): string
    {
        return (TileType::tryFrom($this->tileType)?->getLabel() ?? 'Kachel').' hinzufügen';
    }

    public function form(Schema $schema): Schema
    {
        $type = $this->type();

        return $schema
            ->components([
                Callout::make('Neue Kachel auf „'.$this->section()->title.'“')
                    ->description($type->description().' Sie wird am Ende der Seite eingefügt und lässt sich danach verschieben.')
                    ->info(),
                ...TileForm::components($type),
            ])
            ->columns(2)
            ->statePath('data');
    }

    protected function persist(array $data): void
    {
        $this->section()->tiles()->create(TilesRelationManager::withType($data, $this->type()));
    }

    protected function type(): TileType
    {
        return TileType::tryFrom($this->tileType) ?? abort(404);
    }

    protected function section(): Section
    {
        $section = Section::query()->findOrFail($this->sectionId);

        Gate::authorize('update', $section);

        return $section;
    }
}
