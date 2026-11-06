<?php

namespace App\Filament\Resources\Sections\Pages;

use App\Filament\Resources\Sections\SectionResource;
use App\Models\Section;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Icons\Heroicon;

class ListSections extends ListRecords
{
    protected static string $resource = SectionResource::class;

    /**
     * Reordering sorts flat by the sort column, so it is aligned with the tree order first.
     */
    #[Exposed]
    public function toggleTableReordering(): void
    {
        if (! $this->isTableReordering()) {
            Section::renumberInTreeOrder();
        }

        parent::toggleTableReordering();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('arrangeOnHomepage')
                ->label('Auf der Homepage anordnen')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->url(fn (): string => route('homepage.index', ['anordnen' => 1]))
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) auth()->user()?->can('reorder', Section::class)),
            CreateAction::make(),
        ];
    }
}
