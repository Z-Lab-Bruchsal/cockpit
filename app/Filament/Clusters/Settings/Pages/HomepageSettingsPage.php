<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Pages\DesignPage;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

class HomepageSettingsPage extends CompanySettingsSubPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Homepage';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Homepage';

    protected static ?string $title = 'Homepage';

    protected static ?string $slug = 'homepage';

    protected function settingsComponents(): array
    {
        return [
            TextInput::make('homepage_title')
                ->label('Titel der Homepage')
                ->helperText('Leer = Firmenname')
                ->maxLength(255),
            TextInput::make('homepage_tagline')
                ->label('Untertitel / Slogan')
                ->maxLength(255),
            Text::make(new HtmlString('Farben der Homepage werden unter <a href="'.e(DesignPage::getUrl()).'" class="font-semibold underline">Design</a> eingestellt – oder direkt auf der Homepage mit „Farben gestalten“.'))
                ->columnSpanFull(),
            TextInput::make('newsletter_url')
                ->label('Link „Zum Newsletter“')
                ->helperText('Wimpel unter dem Kopfbereich. Leer = kein Wimpel.')
                ->url()
                ->maxLength(500),
            TextInput::make('donation_url')
                ->label('Link „Jetzt spenden“')
                ->helperText('Wimpel unter dem Kopfbereich. Leer = kein Wimpel.')
                ->url()
                ->maxLength(500),
        ];
    }
}
