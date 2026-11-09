<?php

namespace App\Filament\Pages\Inline;

use App\Filament\Resources\Sections\Schemas\ContentBlocks;
use App\Models\Section;
use App\Services\Homepage\ContentIds;
use Filament\Forms\Components\Builder\Block;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/**
 * Edits a single content block of a section directly from the homepage.
 */
class EditBlockInline extends InlineEditPage
{
    protected static ?string $slug = 'homepage/block';

    #[Locked]
    public int $sectionId;

    #[Locked]
    public string $blockId;

    #[Locked]
    public string $blockType = '';

    public function mount(): void
    {
        $this->sectionId = request()->integer('section');
        $this->blockId = (string) request()->query('block', '');

        $section = $this->section();
        $index = ContentIds::blockIndex($section->content, $this->blockId);

        abort_if($index === null || $this->block((string) ($section->content[$index]['type'] ?? '')) === null, 404);

        $this->blockType = (string) $section->content[$index]['type'];

        $this->form->fill($section->content[$index]['data'] ?? []);
    }

    public function getTitle(): string
    {
        return ($this->block($this->blockType)?->getLabel() ?? 'Block').' bearbeiten';
    }

    public function form(Schema $schema): Schema
    {
        $block = $this->block($this->blockType);

        return $schema
            ->components([
                Callout::make('Seite „'.$this->section()->title.'“')
                    ->description('Änderungen sind nach dem Speichern sofort auf der Homepage sichtbar.')
                    ->info(),
                ...($block?->getDefaultChildComponents() ?? []),
            ])
            ->columns($block?->getColumns('lg') ?? 1)
            ->statePath('data');
    }

    protected function persist(array $data): void
    {
        $section = $this->section();
        $content = $section->content ?? [];
        $index = ContentIds::blockIndex($content, $this->blockId);

        abort_if($index === null, 404);

        $content[$index]['data'] = [...$data, 'block_id' => $this->blockId];

        $section->update(['content' => $content]);
    }

    protected function section(): Section
    {
        $section = Section::query()->findOrFail($this->sectionId);

        Gate::authorize('update', $section);

        return $section;
    }

    protected function block(string $type): ?Block
    {
        foreach (ContentBlocks::blocks() as $block) {
            if ($block->getName() === $type) {
                return $block;
            }
        }

        return null;
    }
}
