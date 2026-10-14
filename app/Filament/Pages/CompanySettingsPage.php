<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Courses\Schemas\SchoolClassSettingsFields;
use App\Filament\Resources\Sections\Schemas\ContentBlocks;
use App\Models\CompanySetting;
use App\Models\Section as SectionModel;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
        $this->form->fill($this->getRecord()->attributesToArray());
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
                                        static::colorPicker('cockpit_primary_color')
                                            ->label('Hauptfarbe')
                                            ->required(),
                                        static::colorPicker('cockpit_success_color')
                                            ->label('Erfolg'),
                                        static::colorPicker('cockpit_warning_color')
                                            ->label('Warnung'),
                                        static::colorPicker('cockpit_danger_color')
                                            ->label('Gefahr'),
                                        static::colorPicker('cockpit_info_color')
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
                                static::colorPicker('primary_color')
                                    ->label('Hauptfarbe')
                                    ->helperText('Buttons, Links, hervorgehobene Kacheln')
                                    ->required(),
                                static::colorPicker('secondary_color')
                                    ->label('Akzentfarbe')
                                    ->helperText('Hintergrund von Kopfbereich und Footer')
                                    ->required(),
                            ])
                            ->columns(2),
                        Tab::make('Footer')
                            ->icon(Heroicon::OutlinedBars3BottomLeft)
                            ->schema([
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
                        Tab::make('Kursbuchung')
                            ->icon(Heroicon::OutlinedTicket)
                            ->schema([
                                TextInput::make('hold_minutes')
                                    ->label('Reservierung im Warenkorb [min]')
                                    ->helperText('So lange sind Plätze während der Buchung für andere gesperrt.')
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

    protected static function colorPicker(string $name): ColorPicker
    {
        return ColorPicker::make($name)
            ->hex()
            ->regex('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/');
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
