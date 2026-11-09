<?php

namespace App\Filament\Clusters\Settings\Pages;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CourseBookingPage extends CompanySettingsSubPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Kurse';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Kursbuchung';

    protected static ?string $title = 'Kursbuchung';

    protected static ?string $slug = 'kursbuchung';

    protected function settingsComponents(): array
    {
        return [
            TextInput::make('hold_minutes')
                ->label('Zeit zum Buchen [min]')
                ->helperText('Zeit vom Klick auf „Buchen“ bis zum Absenden. So lange sind die Plätze für andere gesperrt, danach werden sie wieder frei.')
                ->numeric()
                ->minValue(1)
                ->maxValue(240)
                ->required(),
            TextInput::make('confirmation_hours')
                ->label('Frist für E-Mail-Bestätigung [h]')
                ->helperText('Danach verfällt eine unbestätigte Anmeldung.')
                ->numeric()
                ->minValue(1)
                ->maxValue(336)
                ->required(),
            Section::make('Buchungsgrenzen')
                ->description('Verhindert, dass sich einzelne Familien sehr viele Kurse auf einmal sichern. Anmeldungen im Cockpit sind ausgenommen.')
                ->schema([
                    TextInput::make('booking_limit_per_hour')
                        ->label('Max. Kurse pro Kind und Stunde')
                        ->helperText('Gezählt werden die letzten 60 Minuten. 0 = keine Begrenzung')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(50)
                        ->required(),
                    TextInput::make('booking_limit_per_day')
                        ->label('Max. Kurse pro Kind in 24 Stunden')
                        ->helperText('Gezählt werden die letzten 24 Stunden. 0 = keine Begrenzung')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(100)
                        ->required(),
                    TextInput::make('max_seats_per_registration')
                        ->label('Max. Kinder pro Anmeldung')
                        ->helperText('Auswahl „Anzahl Kinder“ auf der Kursseite')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(20)
                        ->required(),
                ])
                ->columns(3)
                ->columnSpanFull(),
            TextInput::make('digest_hours')
                ->label('Kursinfos sammeln [h]')
                ->helperText('Infos zu neuen Kursen werden gesammelt und verschickt, wenn so lange kein weiterer passender Kurs dazugekommen ist. Eltern können das in ihrem Konto ändern.')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(168)
                ->required(),
            TextInput::make('waitlist_offer_hours')
                ->label('Frist für Nachrücker [h]')
                ->helperText('So lange hat eine Familie Zeit, einen frei gewordenen Platz anzunehmen. Pro Kurs und Durchführung überschreibbar.')
                ->numeric()
                ->minValue(1)
                ->maxValue(336)
                ->required(),
        ];
    }
}
