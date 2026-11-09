<?php

namespace App\Filament\Clusters\Settings\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class OrderReportSettingsPage extends CompanySettingsSubPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Bestellungen';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Wöchentlicher Bericht';

    protected static ?string $title = 'Wöchentlicher Bestellbericht';

    protected static ?string $slug = 'bestellbericht-versand';

    protected function settingsComponents(): array
    {
        return [
            Toggle::make('order_report_enabled')
                ->label('Bestellbericht jede Woche per E-Mail verschicken')
                ->helperText('Mit Excel- und PDF-Anhang für die Buchhaltung. Enthält alle Bestellungen seit dem letzten Bericht.')
                ->live()
                ->columnSpanFull(),
            Select::make('order_report_weekday')
                ->label('Wochentag')
                ->options([1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'])
                ->selectablePlaceholder(false)
                ->required(),
            Select::make('order_report_time')
                ->label('Uhrzeit')
                ->options(collect(range(5, 22))->mapWithKeys(fn (int $hour): array => [sprintf('%02d:00', $hour) => sprintf('%02d:00 Uhr', $hour)])->all())
                ->selectablePlaceholder(false)
                ->required(),
            TagsInput::make('order_report_recipients')
                ->label('Empfänger')
                ->placeholder('E-Mail-Adresse eingeben und Enter drücken')
                ->nestedRecursiveRules(['email'])
                ->required(fn (Get $get): bool => (bool) $get('order_report_enabled'))
                ->columnSpanFull(),
            Text::make(fn (): string => ($sentAt = $this->getRecord()->order_report_last_sent_at)
                ? 'Zuletzt verschickt: '.$sentAt->format('d.m.Y H:i').' Uhr'
                : 'Noch nicht verschickt.')
                ->columnSpanFull(),
        ];
    }
}
