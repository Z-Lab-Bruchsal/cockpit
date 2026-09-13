<?php

namespace App\Filament\Pages;

use App\Models\CompanySetting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Override;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class CompanySettingsPage extends Page
{
    protected string $view = 'filament.pages.company-settings-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Firma';

    protected static ?string $title = 'Firmeneinstellungen';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    #[Override]
    public static function canAccess(): bool
    {
        return parent::canAccess() && User::find(filament()->auth()->user()->id)->can('View:CompanySettingsPage');
    }

    public function mount(): void
    {
        $this->form->fill($this->getRecord()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Firmenname')
                    ->maxLength(255),
                Section::make('Adresse')
                    ->schema([
                        TextInput::make('street')
                            ->label('Straße'),
                        TextInput::make('zip')
                            ->label('PLZ'),
                        TextInput::make('city')
                            ->label('Ort'),
                    ])
                    ->columns(3),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->disk('public')
                    ->directory('company')
                    ->visibility('public'),
            ])
            ->record($this->getRecord())
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->getRecord()->update($data);

        Notification::make()
            ->success()
            ->title('Gespeichert')
            ->send();
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
