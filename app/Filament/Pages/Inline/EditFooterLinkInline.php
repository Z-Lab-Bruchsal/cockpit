<?php

namespace App\Filament\Pages\Inline;

use App\Livewire\Homepage\FooterEditor;
use App\Models\CompanySetting;
use App\Models\Section;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Livewire\Attributes\Locked;

/**
 * Edits or adds one footer link or social media profile directly from the homepage.
 * Index "neu" appends a new entry.
 */
class EditFooterLinkInline extends InlineEditPage
{
    protected static ?string $slug = 'homepage/footer-link';

    #[Locked]
    public string $kind = 'links';

    #[Locked]
    public ?int $index = null;

    public static function canAccess(): bool
    {
        return parent::canAccess() && (bool) User::find(filament()->auth()->id())?->can('View:CompanySettingsPage');
    }

    public function mount(): void
    {
        $this->kind = (string) request()->query('kind', 'links');
        $index = request()->query('index');
        $this->index = is_numeric($index) ? (int) $index : null;

        abort_unless(array_key_exists($this->kind, FooterEditor::LIST_COLUMNS), 404);

        $entries = $this->entries();

        abort_if($this->index !== null && ! array_key_exists($this->index, $entries), 404);

        $this->form->fill($this->index !== null ? $entries[$this->index] : []);
    }

    public function getTitle(): string
    {
        $what = $this->kind === 'social' ? 'Social-Media-Profil' : 'Footer-Link';

        return $this->index === null ? $what.' hinzufügen' : $what.' bearbeiten';
    }

    public function form(Schema $schema): Schema
    {
        $fields = $this->kind === 'social'
            ? [
                Select::make('platform')
                    ->label('Plattform')
                    ->options(CompanySetting::SOCIAL_PLATFORMS)
                    ->required(),
                TextInput::make('url')
                    ->label('Adresse')
                    ->url()
                    ->required()
                    ->maxLength(500),
            ]
            : [
                TextInput::make('label')
                    ->label('Beschriftung')
                    ->required()
                    ->maxLength(60),
                Select::make('section_id')
                    ->label('Bereich der Homepage')
                    ->options(fn (): array => Section::query()->where('is_home', false)->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable()
                    ->requiredWithout('url'),
                TextInput::make('url')
                    ->label('oder externe Adresse')
                    ->url()
                    ->maxLength(500),
            ];

        return $schema
            ->components($fields)
            ->statePath('data');
    }

    protected function persist(array $data): void
    {
        $settings = CompanySetting::current();
        $column = FooterEditor::LIST_COLUMNS[$this->kind];
        $entries = $this->entries();

        if ($this->index === null) {
            $entries[] = $data;
        } else {
            abort_unless(array_key_exists($this->index, $entries), 404);
            $entries[$this->index] = $data;
        }

        $settings->update([$column => array_values($entries)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function entries(): array
    {
        return array_values(CompanySetting::current()->{FooterEditor::LIST_COLUMNS[$this->kind]} ?? []);
    }
}
