<?php

namespace App\Filament\Resources\Courses\Schemas;

use App\Enums\CourseAudience;
use App\Filament\Resources\Sections\Schemas\ContentBlocks;
use App\Models\CompanySetting;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('course')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Visitenkarte')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255),
                                Textarea::make('teaser')
                                    ->label('Kurztext')
                                    ->helperText('Erscheint auf der Visitenkarte (max. 300 Zeichen).')
                                    ->rows(3)
                                    ->maxLength(300),
                                ContentBlocks::imageUpload('card_image_path')
                                    ->label('Bild für die Visitenkarte')
                                    ->helperText('Leer = großes Bild der Detailseite bzw. Ersatzbild der Vorlage.')
                                    ->directory('courses'),
                                ContentBlocks::altText('card_image_alt', 'card_image_path'),
                            ]),
                        Tab::make('Detailseite')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                ContentBlocks::imageUpload('image_path')
                                    ->label('Großes Bild')
                                    ->directory('courses'),
                                ContentBlocks::altText('image_alt', 'image_path'),
                                RichEditor::make('description')
                                    ->label('Beschreibung')
                                    ->toolbarButtons([
                                        ['bold', 'italic', 'underline', 'link'],
                                        ['h2', 'h3'],
                                        ['bulletList', 'orderedList', 'blockquote'],
                                        ['undo', 'redo'],
                                    ]),
                            ]),
                        Tab::make('Einstellungen')
                            ->icon(Heroicon::OutlinedCog6Tooth)
                            ->schema([
                                Select::make('audience')
                                    ->label('Zielgruppe')
                                    ->options(CourseAudience::class)
                                    ->default(CourseAudience::Kids)
                                    ->required()
                                    ->live(),
                                Select::make('course_template_id')
                                    ->label('Darstellungsvorlage')
                                    ->relationship('template', 'name')
                                    ->placeholder('Standardvorlage')
                                    ->preload(),
                                Select::make('interests')
                                    ->label('Interessen')
                                    ->relationship('interests', 'name', fn ($query) => $query->orderBy('sort'))
                                    ->multiple()
                                    ->preload()
                                    ->helperText('Damit wird der Kurs eingeordnet und Interessierte können benachrichtigt werden.'),
                                Select::make('belts')
                                    ->label('Gürtel')
                                    ->relationship('belts', 'name')
                                    ->multiple()
                                    ->preload(),
                                TextInput::make('min_age')
                                    ->label('Mindestalter')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(99)
                                    ->suffix('Jahre')
                                    ->visible(fn (Get $get): bool => static::isForKids($get('audience'))),
                                TextInput::make('max_age')
                                    ->label('Höchstalter')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(99)
                                    ->suffix('Jahre')
                                    ->gte('min_age')
                                    ->visible(fn (Get $get): bool => static::isForKids($get('audience'))),
                                TextInput::make('duration')
                                    ->label('Dauer je Termin')
                                    ->helperText('Vorschlag für das Ende neuer Termine.')
                                    ->suffix('min')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1),
                                TextInput::make('default_capacity')
                                    ->label('Plätze (Vorschlag)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(10),
                                Toggle::make('default_waitlist_enabled')
                                    ->label('Warteliste (Vorschlag)')
                                    ->default(true),
                                Select::make('responsible_user_id')
                                    ->label('Kursverantwortliche*r')
                                    ->relationship('responsibleUser', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn (): ?int => auth()->id())
                                    ->helperText('Entscheidet über Freigaben (Geschwister, Warteliste) und bekommt die Anfragen per E-Mail.'),
                                TextInput::make('waitlist_offer_hours')
                                    ->label('Frist für Nachrücker')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(336)
                                    ->suffix('Stunden')
                                    ->placeholder(fn (): string => 'Standard: '.CompanySetting::current()->waitlist_offer_hours)
                                    ->helperText('Leer = Einstellung aus den Firmeninfos.'),
                                Toggle::make('is_published')
                                    ->label('Auf der Homepage veröffentlicht'),
                                TextInput::make('slug')
                                    ->label('Adresse')
                                    ->prefix('/kurse/')
                                    ->helperText('Leer = aus dem Namen erzeugt.')
                                    ->alphaDash()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function isForKids(mixed $audience): bool
    {
        return ($audience instanceof CourseAudience ? $audience : CourseAudience::tryFrom((string) $audience)) !== CourseAudience::SchoolClass;
    }
}
