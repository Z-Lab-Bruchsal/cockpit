<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Forms\HexColorPicker;
use App\Models\CompanySetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CockpitPage extends CompanySettingsSubPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = 'Allgemein';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Cockpit';

    protected static ?string $title = 'Cockpit';

    protected static ?string $slug = 'cockpit';

    protected function settingsComponents(): array
    {
        return [
            TextInput::make('app_title')
                ->label('Titel der Anwendung')
                ->helperText('Wird im Cockpit, im Browser-Tab und als Absender von E-Mails angezeigt.')
                ->required()
                ->maxLength(255),
            Section::make('Farben Cockpit')
                ->description('Leer = Standardfarbe.')
                ->schema([
                    HexColorPicker::make('cockpit_primary_color')
                        ->label('Hauptfarbe')
                        ->required(),
                    HexColorPicker::make('cockpit_success_color')
                        ->label('Erfolg'),
                    HexColorPicker::make('cockpit_warning_color')
                        ->label('Warnung'),
                    HexColorPicker::make('cockpit_danger_color')
                        ->label('Gefahr'),
                    HexColorPicker::make('cockpit_info_color')
                        ->label('Info'),
                ])
                ->columns(5)
                ->columnSpanFull(),
        ];
    }

    protected function formColumns(): int
    {
        return 1;
    }

    /**
     * New cockpit colors only apply after a reload.
     */
    protected function shouldReloadAfterSave(CompanySetting $record): bool
    {
        return $record->isDirty(['cockpit_primary_color', 'cockpit_success_color', 'cockpit_warning_color', 'cockpit_danger_color', 'cockpit_info_color']);
    }
}
