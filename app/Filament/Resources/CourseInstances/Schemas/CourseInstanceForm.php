<?php

namespace App\Filament\Resources\CourseInstances\Schemas;

use App\Enums\CourseInstanceStatus;
use App\Enums\CourseParticipation;
use App\Enums\CourseProminence;
use App\Filament\Resources\Courses\Schemas\SchoolClassSettingsFields;
use App\Filament\Resources\Sections\Schemas\ContentBlocks;
use App\Models\CompanySetting;
use App\Models\Course;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class CourseInstanceForm
{
    protected static function isPublished(mixed $status): bool
    {
        return ($status instanceof CourseInstanceStatus ? $status : CourseInstanceStatus::tryFrom((string) $status)) === CourseInstanceStatus::Published;
    }

    protected static function isDropIn(mixed $participation): bool
    {
        return ($participation instanceof CourseParticipation ? $participation : CourseParticipation::tryFrom((string) $participation)) === CourseParticipation::DropIn;
    }

    /**
     * Fields for the homepage card and detail page of an instance. Also used when editing directly on the homepage.
     *
     * @return array<int, Component>
     */
    public static function displayFields(): array
    {
        return [
            TextInput::make('card_label')
                ->label('Etikett auf dem Bild')
                ->placeholder('Löten lernen. Selber machen.')
                ->helperText('Erscheint als Schild auf dem Kartenbild. Leer = kein Etikett.')
                ->maxLength(60),
            TextInput::make('card_label_sub')
                ->label('Zweites Etikett')
                ->placeholder(fn (Get $get): string => static::isDropIn($get('participation')) ? 'Ohne Anmeldung' : 'z. B. Nur in den Ferien')
                ->helperText(fn (Get $get): ?string => static::isDropIn($get('participation')) ? 'Leer = „Ohne Anmeldung“' : null)
                ->maxLength(40),
            Textarea::make('teaser')
                ->label('Kurztext')
                ->placeholder(fn (Get $get): ?string => Course::find($get('course_id'))?->teaser)
                ->rows(2)
                ->maxLength(300)
                ->columnSpanFull(),
            ContentBlocks::imageUpload('card_image_path')
                ->label('Kartenbild')
                ->directory('courses')
                ->helperText('Leer = Bild vom Kurs. Text am besten nicht ins Bild schreiben, dafür gibt es das Etikett.'),
            ContentBlocks::altText('card_image_alt', 'card_image_path'),
            ContentBlocks::imageUpload('image_path')
                ->label('Bild auf der Detailseite')
                ->directory('courses')
                ->helperText('Leer = Kartenbild bzw. Bild vom Kurs.'),
            ContentBlocks::altText('image_alt', 'image_path'),
            ContentBlocks::fullRichEditor('description')
                ->label('Beschreibung')
                ->helperText('Leer = Beschreibung vom Kurs. Hier z. B. Hinweise nur für diese Durchführung ergänzen.')
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Durchführung')
                    ->schema([
                        Select::make('course_id')
                            ->label('Kurs')
                            ->relationship('course', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn (): ?int => request()->integer('course') ?: null)
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $course = Course::find($state);

                                if ($course !== null) {
                                    $set('capacity', $course->default_capacity);
                                    $set('waitlist_enabled', $course->default_waitlist_enabled);
                                    $set('participation', $course->isForSchoolClasses() ? CourseParticipation::Registration : $course->default_participation);
                                }
                            }),
                        TextInput::make('title')
                            ->label('Eigener Titel')
                            ->helperText('Leer = Name des Kurses, z. B. „Herbstferien 2026“.')
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Status')
                            ->options(CourseInstanceStatus::class)
                            ->default(CourseInstanceStatus::Draft)
                            ->required()
                            ->live(),
                        ToggleButtons::make('participation')
                            ->label('Teilnahme')
                            ->options(CourseParticipation::class)
                            ->default(fn (): CourseParticipation => Course::find(request()->integer('course'))?->default_participation ?? CourseParticipation::Registration)
                            ->inline()
                            ->required()
                            ->live()
                            ->hidden(fn (Get $get): bool => (bool) Course::find($get('course_id'))?->isForSchoolClasses()),
                        Select::make('prominence')
                            ->label('Darstellung in Angebotslisten')
                            ->options(CourseProminence::class)
                            ->default(CourseProminence::Standard)
                            ->selectablePlaceholder(false)
                            ->required(),
                        Toggle::make('notify_interested')
                            ->label('Interessierte gleich benachrichtigen')
                            ->helperText('Der Kurs wird für Eltern mit Einwilligung vorgemerkt, deren Kinder zu den Interessen und dem Alter passen. Sie bekommen ihn mit der nächsten Sammelmail. Später geht das auch über „Interessierte benachrichtigen“.')
                            ->dehydrated(false)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && static::isPublished($get('status'))),
                        TextInput::make('location')
                            ->label('Ort')
                            ->default('Z-LAB Bruchsal')
                            ->maxLength(255),
                        Select::make('responsible_user_id')
                            ->label('Kursverantwortliche*r')
                            ->relationship('responsibleUser', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder(fn (Get $get): string => 'vom Kurs: '.(Course::find($get('course_id'))?->responsibleUser?->name ?? '–'))
                            ->helperText('Nur ausfüllen, wenn für diese Durchführung jemand anderes verantwortlich ist.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Schulklassen: Erinnerungen und Fristen')
                    ->description('Jede Durchführung ist ein Termin für genau eine Klasse. Leer = Einstellung vom Kurs bzw. aus den Einstellungen.')
                    ->schema(SchoolClassSettingsFields::overrides(fn (string $field, Get $get): string => 'vom Kurs: '.SchoolClassSettingsFields::inheritedValue(Course::find($get('course_id')), $field)))
                    ->columns(2)
                    ->visible(fn (Get $get): bool => (bool) Course::find($get('course_id'))?->isForSchoolClasses())
                    ->columnSpanFull(),
                Section::make('Wann kann man vorbeikommen?')
                    ->description('Für Angebote ohne Anmeldung gibt es keine einzelnen Termine und keine Plätze.')
                    ->schema([
                        TextInput::make('schedule_text')
                            ->label('Zeiten')
                            ->placeholder('Mittwochs · 14:00–17:00')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('schedule_note')
                            ->label('Hinweis')
                            ->placeholder('Außer in den Schulferien')
                            ->maxLength(255),
                        DatePicker::make('available_from')
                            ->label('Angebot ab')
                            ->helperText('Leer = sofort sichtbar'),
                        DatePicker::make('available_until')
                            ->label('Angebot bis')
                            ->helperText('Leer = unbefristet')
                            ->afterOrEqual('available_from'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get): bool => static::isDropIn($get('participation')))
                    ->columnSpanFull(),
                Section::make('Plätze & Anmeldung')
                    ->hidden(fn (Get $get): bool => static::isDropIn($get('participation')))
                    ->schema([
                        TextInput::make('max_class_size')
                            ->label('Maximale Klassengröße')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(200)
                            ->suffix('Kinder')
                            ->placeholder(fn (Get $get): string => 'vom Kurs: '.(Course::find($get('course_id'))?->max_class_size ?? 'keine Begrenzung'))
                            ->visible(fn (Get $get): bool => (bool) Course::find($get('course_id'))?->isForSchoolClasses()),
                        TextInput::make('capacity')
                            ->label('Plätze')
                            ->hidden(fn (Get $get): bool => (bool) Course::find($get('course_id'))?->isForSchoolClasses())
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default(fn (): ?int => Course::find(request()->integer('course'))?->default_capacity ?? 10),
                        Toggle::make('waitlist_enabled')
                            ->label('Warteliste')
                            ->hidden(fn (Get $get): bool => (bool) Course::find($get('course_id'))?->isForSchoolClasses())
                            ->default(fn (): bool => Course::find(request()->integer('course'))?->default_waitlist_enabled ?? true)
                            ->live(),
                        TextInput::make('waitlist_limit')
                            ->label('Max. Wartelistenplätze')
                            ->helperText('Leer = unbegrenzt')
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn (Get $get): bool => (bool) $get('waitlist_enabled') && ! Course::find($get('course_id'))?->isForSchoolClasses()),
                        TextInput::make('waitlist_offer_hours')
                            ->label('Frist für Nachrücker')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(336)
                            ->suffix('Stunden')
                            ->placeholder(fn (Get $get): string => 'vom Kurs: '.(Course::find($get('course_id'))?->waitlist_offer_hours ?? CompanySetting::current()->waitlist_offer_hours))
                            ->visible(fn (Get $get): bool => (bool) $get('waitlist_enabled') && ! Course::find($get('course_id'))?->isForSchoolClasses()),
                        DateTimePicker::make('registration_opens_at')
                            ->label('Anmeldung ab')
                            ->helperText('Leer = sofort')
                            ->seconds(false),
                        DateTimePicker::make('registration_closes_at')
                            ->label('Anmeldung bis')
                            ->helperText('Leer = bis zum ersten Termin')
                            ->seconds(false)
                            ->after('registration_opens_at'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Termine')
                    ->hidden(fn (Get $get): bool => static::isDropIn($get('participation')))
                    ->schema([
                        Repeater::make('dates')
                            ->hiddenLabel()
                            ->relationship('dates')
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label('Beginn')
                                    ->seconds(false)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                        if (blank($state) || filled($get('ends_at'))) {
                                            return;
                                        }

                                        $duration = Course::find($get('../../course_id'))?->duration ?? 90;
                                        $set('ends_at', Carbon::parse($state)->addMinutes($duration)->format('Y-m-d H:i:s'));
                                    }),
                                DateTimePicker::make('ends_at')
                                    ->label('Ende')
                                    ->seconds(false)
                                    ->required()
                                    ->after('starts_at'),
                                TextInput::make('location')
                                    ->label('Abweichender Ort')
                                    ->maxLength(255),
                            ])
                            ->columns(3)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->cloneable()
                            ->addActionLabel('Termin hinzufügen')
                            ->itemLabel(fn (array $state): ?string => filled($state['starts_at'] ?? null)
                                ? Carbon::parse($state['starts_at'])->locale('de')->isoFormat('dd, DD.MM.YYYY HH:mm')
                                : null),
                    ])
                    ->columnSpanFull(),
                Section::make('Karte & Detailseite')
                    ->description('So erscheint diese Durchführung auf der Homepage. Leere Felder übernehmen die Angaben vom Kurs.')
                    ->schema(static::displayFields())
                    ->columns(2)
                    ->collapsible()
                    ->columnSpanFull(),
                Section::make('Interne Notizen')
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(3),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }
}
