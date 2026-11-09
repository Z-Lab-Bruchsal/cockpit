<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Resources\Sections\Schemas\ContentBlocks;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CompanyPage extends CompanySettingsSubPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Allgemein';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Firma';

    protected static ?string $title = 'Firma';

    protected static ?string $slug = 'firma';

    protected function settingsComponents(): array
    {
        return [
            TextInput::make('name')
                ->label('Firmenname')
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make('street')
                ->label('Straße'),
            TextInput::make('zip')
                ->label('PLZ'),
            TextInput::make('city')
                ->label('Ort'),
            TextInput::make('contact_email')
                ->label('Kontakt-E-Mail')
                ->email()
                ->maxLength(255),
            TextInput::make('contact_phone')
                ->label('Telefon')
                ->tel()
                ->maxLength(255),
            FileUpload::make('logo_path')
                ->label('Logo')
                ->image()
                ->disk('public')
                ->directory('company')
                ->visibility('public')
                ->columnSpanFull(),
            ContentBlocks::altText('logo_alt', 'logo_path')
                ->helperText('z. B. „Logo Z-LAB“')
                ->columnSpanFull(),
        ];
    }

    protected function formColumns(): int
    {
        return 3;
    }
}
