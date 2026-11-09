<?php

namespace App\Filament\Pages;

use App\Filament\Forms\HexColorPicker;
use App\Filament\Resources\Courses\Schemas\SchoolClassSettingsFields;
use App\Filament\Resources\Sections\Schemas\ContentBlocks;
use App\Models\CompanySetting;
use App\Models\Section as SectionModel;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Override;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class CompanySettingsPage extends Page
{
    protected string $view = 'filament.pages.company-settings-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Firmeninfos';

    protected static ?string $title = 'Firmeninfos';

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
        $this->form->fill([
            ...$this->getRecord()->attributesToArray(),
            'footer_blocks' => $this->getRecord()->footerBlocks(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('settings')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Firma')
                            ->icon(Heroicon::OutlinedBuildingOffice2)
                            ->schema([
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
                            ])
                            ->columns(3),
                        Tab::make('Anwendung')
                            ->icon(Heroicon::OutlinedComputerDesktop)
                            ->schema([
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
                                    ->columns(5),
                            ]),
                        Tab::make('Homepage')
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->schema([
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
                            ])
                            ->columns(2),
                        Tab::make('Footer')
                            ->icon(Heroicon::OutlinedBars3BottomLeft)
                            ->schema([
                                Builder::make('footer_blocks')
                                    ->label('Aufbau des Footers')
                                    ->helperText('Elemente per Ziehen sortieren. Auch direkt auf der Homepage unter „Anordnen“ möglich.')
                                    ->blocks([
                                        Block::make('text')
                                            ->label('Text')
                                            ->icon(Heroicon::OutlinedBars3BottomLeft)
                                            ->schema([
                                                ContentBlocks::blockId(),
                                                ContentBlocks::fullRichEditor('body')
                                                    ->label('Text'),
                                                static::footerWidth(),
                                            ]),
                                        ...collect(['address', 'links', 'social'])->map(fn (string $type): Block => Block::make($type)
                                            ->label(CompanySetting::FOOTER_BLOCK_TYPES[$type])
                                            ->schema([
                                                ContentBlocks::blockId(),
                                                static::footerWidth(),
                                            ]))->all(),
                                    ])
                                    ->blockNumbers(false)
                                    ->collapsible()
                                    ->addActionLabel('Element hinzufügen')
                                    ->columnSpanFull(),
                                TextInput::make('footer_text')
                                    ->label('Copyright-Text')
                                    ->helperText('Erscheint als „© Jahr Text“. Leer = Firmenname.')
                                    ->maxLength(255),
                                Repeater::make('footer_links')
                                    ->label('Links')
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Beschriftung')
                                            ->required()
                                            ->maxLength(60),
                                        Select::make('section_id')
                                            ->label('Bereich der Homepage')
                                            ->options(fn (): array => SectionModel::query()->orderBy('title')->pluck('title', 'id')->all())
                                            ->searchable()
                                            ->requiredWithout('url'),
                                        TextInput::make('url')
                                            ->label('oder externe Adresse')
                                            ->url()
                                            ->maxLength(500),
                                    ])
                                    ->columns(3)
                                    ->reorderable()
                                    ->addActionLabel('Link hinzufügen')
                                    ->defaultItems(0),
                                Repeater::make('social_links')
                                    ->label('Social Media')
                                    ->schema([
                                        Select::make('platform')
                                            ->label('Plattform')
                                            ->options(CompanySetting::SOCIAL_PLATFORMS)
                                            ->required(),
                                        TextInput::make('url')
                                            ->label('Adresse')
                                            ->url()
                                            ->required()
                                            ->maxLength(500),
                                    ])
                                    ->columns(2)
                                    ->reorderable()
                                    ->addActionLabel('Profil hinzufügen')
                                    ->defaultItems(0),
                            ]),
                        Tab::make('Bestellungen')
                            ->icon(Heroicon::OutlinedShoppingBag)
                            ->schema([
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
                            ])
                            ->columns(2),
                        Tab::make('Kursbuchung')
                            ->icon(Heroicon::OutlinedTicket)
                            ->schema([
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
                            ])
                            ->columns(2),
                        Tab::make('Schulklassen')
                            ->icon(Heroicon::OutlinedAcademicCap)
                            ->schema([
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
                            ])
                            ->columns(3),
                    ])
                    ->columnSpanFull(),
            ])
            ->record($this->getRecord())
            ->statePath('data');
    }

    protected static function footerWidth(): Select
    {
        return Select::make('width')
            ->label('Breite')
            ->options(SectionModel::TILE_WIDTHS)
            ->default(2)
            ->selectablePlaceholder(false)
            ->required();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $record = $this->getRecord();
        $record->fill($data);
        $cockpitColorsChanged = $record->isDirty(['cockpit_primary_color', 'cockpit_success_color', 'cockpit_warning_color', 'cockpit_danger_color', 'cockpit_info_color']);
        $record->save();

        Notification::make()
            ->success()
            ->title('Gespeichert')
            ->send();

        if ($cockpitColorsChanged) {
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
