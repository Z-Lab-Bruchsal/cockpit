<?php

namespace App\Filament\Resources\Sections\Schemas;

use App\Enums\SectionLayout;
use App\Models\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaSection::make('Bereich')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titel')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?Section $record): void {
                                if ($record === null && blank($get('slug'))) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Kurzname (Anker / URL)')
                            ->helperText('Adresse der Seite, z. B. /workshops')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->rule(Rule::notIn(Section::RESERVED_SLUGS))
                            ->validationMessages([
                                'not_in' => 'Dieser Kurzname ist für die Homepage reserviert.',
                            ]),
                        Select::make('parent_id')
                            ->label('Übergeordneter Bereich')
                            ->placeholder('— Hauptbereich —')
                            ->options(fn (?Section $record): array => static::parentOptions($record))
                            ->searchable(),
                        Select::make('layout')
                            ->label('Kachel-Darstellung')
                            ->options(SectionLayout::class)
                            ->default(SectionLayout::Standard)
                            ->required(),
                        Toggle::make('is_visible')
                            ->label('Sichtbar')
                            ->default(true),
                        Toggle::make('show_title')
                            ->label('Titel anzeigen')
                            ->default(true),
                        Toggle::make('show_in_navigation')
                            ->label('In Kopfzeile verlinken')
                            ->helperText('Nur bei Hauptbereichen.')
                            ->default(true),
                        Toggle::make('is_standalone')
                            ->label('Nicht als Kachel anzeigen')
                            ->helperText('z. B. Impressum oder Datenschutz – verlinken unter Firmeninfos → Footer.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                SchemaSection::make('Kachel')
                    ->description('So erscheint der Bereich als Kachel auf der übergeordneten Ebene.')
                    ->schema([
                        Textarea::make('teaser')
                            ->label('Kurztext')
                            ->rows(3)
                            ->maxLength(300),
                        ContentBlocks::imageUpload('tile_image_path')
                            ->label('Kachelbild'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                SchemaSection::make('Inhalt der Seite')
                    ->schema([
                        ContentBlocks::make(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * All sections except the record itself and its descendants, labelled with their position in the tree.
     *
     * @return array<int, string>
     */
    protected static function parentOptions(?Section $record): array
    {
        $excludedIds = $record?->exists ? $record->selfAndDescendantIds() : [];
        $options = [];

        $appendChildren = function (?int $parentId, int $depth) use (&$appendChildren, &$options, $excludedIds): void {
            Section::query()
                ->where('parent_id', $parentId)
                ->orderBy('sort')
                ->get()
                ->each(function (Section $section) use ($appendChildren, &$options, $excludedIds, $depth): void {
                    if (in_array($section->id, $excludedIds, true)) {
                        return;
                    }

                    $options[$section->id] = str_repeat('— ', $depth).$section->title;
                    $appendChildren($section->id, $depth + 1);
                });
        };

        $appendChildren(null, 0);

        return $options;
    }
}
