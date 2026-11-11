<?php

namespace App\Filament\Resources\Sections\Schemas;

use App\Enums\CourseAudience;
use App\Enums\TileStyle;
use App\Enums\TileType;
use App\Models\Course;
use App\Models\Interest;
use App\Models\Tile;
use App\Services\Homepage\LinkTarget;
use App\Services\Homepage\TileGrid;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

/**
 * Form of a tile. The type is fixed when the tile is created, so only the fields of that type are shown.
 * Used by the tiles relation manager of a section and by the inline editors on the homepage.
 */
class TileForm
{
    /**
     * @return array<Component|Field>
     */
    public static function components(TileType $type): array
    {
        return [
            ...match ($type) {
                TileType::Content => static::contentFields(),
                TileType::Section => static::sectionFields(),
                TileType::Courses => static::coursesFields(),
                default => [
                    Group::make(ContentBlocks::fieldsFor($type->value))
                        ->statePath('settings')
                        ->columns(ContentBlocks::block($type->value)?->getColumns('lg') ?? 1)
                        ->columnSpanFull(),
                ],
            },
            SchemaSection::make('Größe & Sichtbarkeit')
                ->schema([
                    Select::make('width')
                        ->label($type === TileType::Courses ? 'Breite im Anordnen-Modus' : 'Breite')
                        ->helperText('Auf dem Handy immer volle Breite.')
                        ->options(Tile::WIDTHS)
                        ->default($type->defaultWidth())
                        ->selectablePlaceholder(false)
                        ->required()
                        ->hidden($type === TileType::Courses),
                    Select::make('height')
                        ->label('Höhe')
                        ->options(Tile::HEIGHTS)
                        ->default(1)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->hidden($type === TileType::Courses),
                    Toggle::make('is_visible')
                        ->label('Sichtbar')
                        ->default(true),
                ])
                ->columns(3)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return list<Component|Field>
     */
    protected static function contentFields(): array
    {
        return [
            Select::make('style')
                ->label('Darstellung')
                ->options(TileStyle::class)
                ->default(TileStyle::Standard)
                ->selectablePlaceholder(false)
                ->required()
                ->live(),
            TextInput::make('eyebrow')
                ->label('Dachzeile')
                ->helperText('Kleine Zeile über der Überschrift.')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => static::styleValue($get('style')) === TileStyle::Hero),
            TextInput::make('heading')
                ->label('Überschrift')
                ->maxLength(255),
            RichEditor::make('text')
                ->label('Text')
                ->toolbarButtons([['bold', 'italic']])
                ->columnSpanFull(),
            ...LinkTargetFields::make(LinkTarget::NONE, LinkTargetFields::TILE_COLUMNS),
            ContentBlocks::imageUpload('image_path')
                ->label('Bild'),
            ContentBlocks::altText('image_alt', 'image_path'),
        ];
    }

    /**
     * @return list<Component|Field>
     */
    protected static function sectionFields(): array
    {
        return [
            Select::make('link_section_id')
                ->label('Bereich')
                ->helperText('Titel, Kurztext und Bild kommen aus den Kachel-Vorgaben des Bereichs, solange du sie hier nicht überschreibst.')
                ->options(fn (): array => LinkTargetFields::sectionOptions())
                ->searchable()
                ->required(),
            TextInput::make('link_anchor')
                ->label('Sprungmarke')
                ->helperText('Optional, z. B. „team“ für …/ueber-uns#team')
                ->prefix('#')
                ->alphaDash()
                ->maxLength(60),
            SchemaSection::make('Überschreiben')
                ->description('Optional, leer = Vorgabe des Bereichs.')
                ->schema([
                    Select::make('style')
                        ->label('Darstellung')
                        ->placeholder('Wie Kachelfarbe des Bereichs')
                        ->options([
                            TileStyle::Standard->value => TileStyle::Standard->getLabel(),
                            TileStyle::Highlighted->value => TileStyle::Highlighted->getLabel(),
                        ]),
                    TextInput::make('heading')
                        ->label('Überschrift')
                        ->maxLength(255),
                    RichEditor::make('text')
                        ->label('Text')
                        ->toolbarButtons([['bold', 'italic']])
                        ->columnSpanFull(),
                    ContentBlocks::imageUpload('image_path')
                        ->label('Bild'),
                    ContentBlocks::altText('image_alt', 'image_path'),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),
        ];
    }

    /**
     * @return list<Component>
     */
    protected static function coursesFields(): array
    {
        $courseOptions = fn (): array => Course::query()->published()->orderBy('name')->pluck('name', 'id')->all();

        return [
            Group::make([
                Select::make('audience')
                    ->label('Zielgruppe')
                    ->placeholder('Alle')
                    ->options(CourseAudience::class)
                    ->live(),
                Select::make('interest_ids')
                    ->label('Interessen')
                    ->helperText('Kurse mit mindestens einem dieser Interessen, z. B. Informatik.')
                    ->placeholder('Alle')
                    ->options(fn (): array => Interest::query()->active()->orderBy('sort')->pluck('name', 'id')->all())
                    ->multiple()
                    ->live(),
                Select::make('include_course_ids')
                    ->label('Zusätzlich diese Kurse')
                    ->helperText('Erscheinen immer, in dieser Reihenfolge vorne. Ohne Zielgruppe und Interessen erscheinen nur diese Kurse.')
                    ->options($courseOptions)
                    ->multiple()
                    ->searchable()
                    ->live(),
                Select::make('exclude_course_ids')
                    ->label('Diese Kurse ausschließen')
                    ->options($courseOptions)
                    ->multiple()
                    ->searchable()
                    ->live(),
                TextInput::make('limit')
                    ->label('Max. Anzahl')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(TileGrid::MAX_COURSES)
                    ->default(6)
                    ->required()
                    ->live(onBlur: true),
                Toggle::make('show_all_link')
                    ->label('Link „Alle Kurse ansehen“ anzeigen')
                    ->default(true),
                Select::make('item_width')
                    ->label('Breite je Kurs')
                    ->options(Tile::WIDTHS)
                    ->default(2)
                    ->selectablePlaceholder(false)
                    ->required(),
                Select::make('item_height')
                    ->label('Höhe je Kurs')
                    ->options(Tile::HEIGHTS)
                    ->default(1)
                    ->selectablePlaceholder(false)
                    ->required(),
                Text::make(fn (Get $get): HtmlString => static::coursesPreview($get))
                    ->columnSpanFull(),
            ])
                ->statePath('settings')
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * Which courses the tile currently shows, so that the effect of the filter is visible right away.
     */
    protected static function coursesPreview(Get $get): HtmlString
    {
        $tile = new Tile(['type' => TileType::Courses, 'settings' => [
            'audience' => $get('audience'),
            'interest_ids' => (array) $get('interest_ids'),
            'include_course_ids' => (array) $get('include_course_ids'),
            'exclude_course_ids' => (array) $get('exclude_course_ids'),
            'limit' => $get('limit'),
        ]]);
        $courses = TileGrid::courses($tile);

        if ($courses->isEmpty()) {
            return new HtmlString('<strong>Aktuell passt kein veröffentlichter Kurs.</strong>');
        }

        $count = $courses->count() === 1 ? '1 Kurs' : $courses->count().' Kurse';

        return new HtmlString('<strong>Aktuell '.$count.':</strong> '.e($courses->pluck('name')->implode(', ')));
    }

    protected static function styleValue(mixed $state): ?TileStyle
    {
        return $state instanceof TileStyle ? $state : TileStyle::tryFrom((string) $state);
    }
}
