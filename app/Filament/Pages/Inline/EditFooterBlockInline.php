<?php

namespace App\Filament\Pages\Inline;

use App\Filament\Resources\Sections\Schemas\ContentBlocks;
use App\Models\CompanySetting;
use App\Models\Section;
use App\Models\User;
use App\Services\Homepage\ContentIds;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Livewire\Attributes\Locked;

/**
 * Edits a text element of the footer directly from the homepage.
 */
class EditFooterBlockInline extends InlineEditPage
{
    protected static ?string $slug = 'homepage/footer';

    protected static ?string $title = 'Footer-Text bearbeiten';

    #[Locked]
    public string $blockId = '';

    public static function canAccess(): bool
    {
        return parent::canAccess() && (bool) User::find(filament()->auth()->id())?->can('View:CompanySettingsPage');
    }

    public function mount(): void
    {
        $this->blockId = (string) request()->query('block', '');

        $block = $this->block();

        $this->form->fill($block['data']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ContentBlocks::fullRichEditor('body')
                    ->label('Text'),
                Select::make('width')
                    ->label('Breite')
                    ->options(Section::TILE_WIDTHS)
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->statePath('data');
    }

    protected function persist(array $data): void
    {
        $settings = CompanySetting::current();
        $blocks = $settings->footerBlocks();
        $index = ContentIds::blockIndex($blocks, $this->blockId);

        abort_if($index === null, 404);

        $blocks[$index]['data'] = [...$blocks[$index]['data'], ...$data, 'block_id' => $this->blockId];

        $settings->update(['footer_blocks' => $blocks]);
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    protected function block(): array
    {
        $blocks = CompanySetting::current()->footerBlocks();
        $index = ContentIds::blockIndex($blocks, $this->blockId);

        abort_if($index === null || $blocks[$index]['type'] !== 'text', 404);

        return $blocks[$index];
    }
}
