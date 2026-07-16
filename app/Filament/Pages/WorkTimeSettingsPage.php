<?php

namespace App\Filament\Pages;

use App\Models\WorkTimeSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class WorkTimeSettingsPage extends Page
{
    protected string $view = 'filament.pages.work-time-settings-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Zeiterfassung';

    protected static ?string $navigationLabel = 'Pausenregeln';

    protected static ?string $title = 'Pausenregeln';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return filament()->auth()->user()->hasRole('zeiterfassung-admin');
    }

    public function mount(): void
    {
        $this->form->fill(WorkTimeSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    TextInput::make('threshold_1_hours')
                        ->label('Grenze 1 (Stunden)')
                        ->numeric()
                        ->step(0.25)
                        ->required(),
                    TextInput::make('break_1_minutes')
                        ->label('Pausendauer 1 (Minuten)')
                        ->numeric()
                        ->required(),
                    TextInput::make('threshold_2_hours')
                        ->label('Grenze 2 (Stunden)')
                        ->numeric()
                        ->step(0.25)
                        ->required(),
                    TextInput::make('break_2_minutes')
                        ->label('Pausendauer 2 (Minuten)')
                        ->numeric()
                        ->required(),
                    TextInput::make('minimum_qualifying_break_minutes')
                        ->label('Mindestdauer je Pause (Minuten)')
                        ->numeric()
                        ->required(),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        WorkTimeSetting::current()->update($data);

        Notification::make()
            ->success()
            ->title('Gespeichert')
            ->send();
    }
}
