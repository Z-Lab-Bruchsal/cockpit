<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Resources\Courses\Schemas\SchoolClassSettingsFields;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SchoolClassesPage extends CompanySettingsSubPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Kurse';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Schulklassen';

    protected static ?string $title = 'Schulklassen';

    protected static ?string $slug = 'schulklassen';

    protected function settingsComponents(): array
    {
        return [
            Toggle::make('school_reminders_enabled')
                ->label('Erinnerungsmails verschicken')
                ->helperText('An die Lehrkraft (mit Bitte um Bestätigung) und an die Kursverantwortlichen. Pro Kurs und Durchführung überschreibbar.')
                ->columnSpanFull(),
            SchoolClassSettingsFields::days('teacher_reminder_days', 'Erinnerung an die Lehrkraft')
                ->helperText('Tage vor dem ersten Termin')
                ->required(),
            SchoolClassSettingsFields::days('staff_reminder_days', 'Erinnerung an Kursverantwortliche*n')
                ->helperText('Tage vor dem ersten Termin; im Cockpit verschiebbar')
                ->required(),
            SchoolClassSettingsFields::days('school_reschedule_deadline_days', 'Stornieren/Verschieben durch die Schule bis')
                ->helperText('Tage vor dem ersten Termin; danach nur noch telefonisch')
                ->required(),
            FileUpload::make('directions_document_path')
                ->label('Anfahrtsskizze')
                ->helperText('Wird an die Erinnerungsmail angehängt, wenn die Klasse ins Z-LAB kommt. PDF oder Bild, max. 5 MB.')
                ->disk('local')
                ->directory('company')
                ->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg'])
                ->maxSize(5120)
                ->downloadable()
                ->columnSpanFull(),
        ];
    }

    protected function formColumns(): int
    {
        return 3;
    }
}
