<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\CompanySetting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Override;

/**
 * One page of the global settings (CompanySetting). Each page only saves its own fields,
 * all of them share the permission "View:CompanySettingsPage".
 *
 * @property-read Schema $form
 */
abstract class CompanySettingsSubPage extends Page
{
    protected string $view = 'filament.pages.company-settings-page';

    protected static ?string $cluster = SettingsCluster::class;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Form fields of this page.
     *
     * @return array<Component|Field>
     */
    abstract protected function settingsComponents(): array;

    #[Override]
    public static function canAccess(): bool
    {
        return parent::canAccess() && (bool) User::find(filament()->auth()->id())?->can('View:CompanySettingsPage');
    }

    public function mount(): void
    {
        $this->form->fill([
            ...$this->getRecord()->attributesToArray(),
            'footer_blocks' => $this->getRecord()->footerBlocks(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->settingsComponents())
            ->columns($this->formColumns())
            ->record($this->getRecord())
            ->statePath('data');
    }

    protected function formColumns(): int
    {
        return 2;
    }

    /**
     * Whether the page has to be reloaded after saving, e.g. because the cockpit colors changed.
     */
    protected function shouldReloadAfterSave(CompanySetting $record): bool
    {
        return false;
    }

    public function save(): void
    {
        $record = $this->getRecord();
        $record->fill($this->form->getState());
        $shouldReload = $this->shouldReloadAfterSave($record);
        $record->save();

        Notification::make()
            ->success()
            ->title('Gespeichert')
            ->send();

        if ($shouldReload) {
            $this->redirect(static::getUrl());
        }
    }

    public function getRecord(): CompanySetting
    {
        return CompanySetting::current();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Speichern')
                ->keyBindings(['mod+s'])
                ->action(fn () => $this->save()),
        ];
    }
}
