<?php

namespace App\Filament\Resources\Sections\Schemas;

use App\Enums\ContactFormTopic;
use App\Enums\CourseAudience;
use App\Enums\CourseParticipation;
use App\Enums\TeamArea;
use App\Models\Interest;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Content blocks shared by everything that is rendered on the homepage.
 * Each block is rendered by resources/views/components/homepage/blocks/{type}.blade.php.
 */
class ContentBlocks
{
    public static function make(string $name = 'content'): Builder
    {
        return Builder::make($name)
            ->label('Inhalt')
            ->blocks(static::blocks())
            ->collapsible()
            ->cloneable()
            ->blockNumbers(false)
            ->addActionLabel('Block hinzufügen')
            ->columnSpanFull();
    }

    /**
     * @return array<Block>
     */
    public static function blocks(): array
    {
        return [
            Block::make('button')
                ->label('Button')
                ->icon(Heroicon::OutlinedCursorArrowRipple)
                ->schema([
                    static::blockId(),
                    TextInput::make('label')
                        ->label('Beschriftung')
                        ->required()
                        ->maxLength(80),
                    ...LinkTargetFields::make(),
                    Select::make('align')
                        ->label('Ausrichtung')
                        ->options(['left' => 'Links', 'center' => 'Mittig', 'right' => 'Rechts'])
                        ->default('center')
                        ->selectablePlaceholder(false),
                    Select::make('variant')
                        ->label('Stil')
                        ->options(['outline' => 'Weiß mit Rahmen', 'filled' => 'Farbig gefüllt'])
                        ->default('outline')
                        ->selectablePlaceholder(false),
                ])
                ->columns(2),
            Block::make('text')
                ->label('Text')
                ->icon(Heroicon::OutlinedBars3BottomLeft)
                ->schema([
                    static::blockId(),
                    static::richEditor('body')
                        ->label('Text')
                        ->required(),
                ]),
            Block::make('free')
                ->label('Freies Schreiben')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->schema([
                    static::blockId(),
                    static::fullRichEditor('body')
                        ->label('Inhalt')
                        ->helperText('Bilder per Büroklammer einfügen, Spalten über das Raster-Symbol. Tabellen- und Spalten-Werkzeuge erscheinen, sobald der Cursor darin steht.')
                        ->required(),
                ]),
            Block::make('image')
                ->label('Bild')
                ->icon(Heroicon::OutlinedPhoto)
                ->schema([
                    static::blockId(),
                    static::imageUpload('image')
                        ->label('Bild')
                        ->required(),
                    static::altText('alt', 'image'),
                    TextInput::make('caption')
                        ->label('Bildunterschrift')
                        ->maxLength(255),
                ]),
            Block::make('text_image')
                ->label('Text mit Bild')
                ->icon(Heroicon::OutlinedNewspaper)
                ->schema([
                    static::blockId(),
                    static::richEditor('body')
                        ->label('Text')
                        ->required()
                        ->columnSpanFull(),
                    static::imageUpload('image')
                        ->label('Bild')
                        ->required(),
                    static::altText('alt', 'image'),
                    Select::make('image_position')
                        ->label('Bildposition')
                        ->options([
                            'left' => 'Links',
                            'right' => 'Rechts',
                        ])
                        ->default('right')
                        ->required(),
                ])
                ->columns(2),
            Block::make('call_to_action')
                ->label('Call-to-Action')
                ->icon(Heroicon::OutlinedCursorArrowRays)
                ->schema([
                    static::blockId(),
                    TextInput::make('heading')
                        ->label('Überschrift')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('text')
                        ->label('Text')
                        ->maxLength(500),
                    TextInput::make('button_label')
                        ->label('Button-Text')
                        ->required()
                        ->maxLength(60),
                    TextInput::make('button_url')
                        ->label('Button-Link')
                        ->helperText('z. B. /kurse, #kontakt oder https://…')
                        ->required()
                        ->maxLength(500),
                ])
                ->columns(2),
            Block::make('offers')
                ->label('Angebote (Durchführungen)')
                ->icon(Heroicon::OutlinedRectangleGroup)
                ->schema([
                    static::blockId(),
                    CheckboxList::make('participations')
                        ->label('Welche Angebote?')
                        ->helperText('Nichts ausgewählt = alle.')
                        ->options(CourseParticipation::class)
                        ->columns(2),
                    Toggle::make('show_filter_tabs')
                        ->label('Filter-Reiter anzeigen')
                        ->helperText('„Alle Angebote / Einfach vorbeikommen / Kurse & Workshops“')
                        ->default(true),
                    Select::make('interest_ids')
                        ->label('Nur Kurse mit diesen Interessen')
                        ->placeholder('Alle')
                        ->options(fn (): array => Interest::query()->active()->pluck('name', 'id')->all())
                        ->multiple(),
                    Select::make('audience')
                        ->label('Zielgruppe')
                        ->placeholder('Alle')
                        ->options(CourseAudience::class),
                    TextInput::make('limit')
                        ->label('Max. Anzahl')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(48)
                        ->default(24),
                ])
                ->columns(2),
            Block::make('course_list')
                ->label('Kursliste')
                ->icon(Heroicon::OutlinedAcademicCap)
                ->schema([
                    static::blockId(),
                    Select::make('interest_ids')
                        ->label('Nur Kurse mit diesen Interessen')
                        ->placeholder('Alle')
                        ->options(fn (): array => Interest::query()->active()->pluck('name', 'id')->all())
                        ->multiple(),
                    Select::make('audience')
                        ->label('Zielgruppe')
                        ->placeholder('Alle')
                        ->options(CourseAudience::class),
                    TextInput::make('limit')
                        ->label('Max. Anzahl')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(48)
                        ->default(6),
                    Toggle::make('show_all_link')
                        ->label('Link „Alle Kurse“ anzeigen')
                        ->default(true),
                ])
                ->columns(2),
            Block::make('course_calendar')
                ->label('Kurskalender')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    static::blockId(),
                    Select::make('audience')
                        ->label('Zielgruppe')
                        ->placeholder('Alle')
                        ->options(CourseAudience::class),
                ]),
            Block::make('contact_form')
                ->label('Kontaktformular')
                ->icon(Heroicon::OutlinedEnvelope)
                ->schema([
                    static::blockId(),
                    static::richEditor('intro')
                        ->label('Einleitung'),
                    Select::make('topic')
                        ->label('Vorausgewähltes Anliegen')
                        ->options(ContactFormTopic::class)
                        ->placeholder('Allgemeine Frage'),
                ]),
            Block::make('team')
                ->label('Team')
                ->icon(Heroicon::OutlinedUserGroup)
                ->schema([
                    static::blockId(),
                    Select::make('areas')
                        ->label('Nur diese Teambereiche')
                        ->placeholder('Alle')
                        ->options(TeamArea::class)
                        ->helperText('Personen, Texte und Reihenfolge werden zentral unter Homepage → Team gepflegt.')
                        ->multiple(),
                    Toggle::make('show_area_headings')
                        ->label('Überschriften je Teambereich anzeigen')
                        ->default(true),
                    static::anchor('team'),
                ])
                ->columns(2),
            Block::make('news')
                ->label('News')
                ->icon(Heroicon::OutlinedMegaphone)
                ->schema([
                    static::blockId(),
                    TextInput::make('limit')
                        ->label('Max. Anzahl')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(12)
                        ->default(3),
                    Toggle::make('show_all_link')
                        ->label('Link „Alle News“ anzeigen')
                        ->default(true),
                    static::anchor('news'),
                ])
                ->columns(2),
            Block::make('contact')
                ->label('Kontakt / Adresse')
                ->icon(Heroicon::OutlinedMapPin)
                ->schema([
                    static::blockId(),
                    static::richEditor('intro')
                        ->label('Einleitung'),
                ]),
        ];
    }

    /**
     * Stable id of a block, see ContentIds. Duplicates from cloning are replaced when saving.
     */
    public static function blockId(): Hidden
    {
        return Hidden::make('block_id')
            ->default(fn (): string => (string) Str::uuid());
    }

    /**
     * The fields of one block type without its block id, e.g. for a tile of that type.
     *
     * @return array<Component|Field>
     */
    public static function fieldsFor(string $type): array
    {
        $block = static::block($type);

        if ($block === null) {
            return [];
        }

        return array_values(array_filter(
            $block->getDefaultChildComponents(),
            fn (mixed $component): bool => ! ($component instanceof Hidden && $component->getName() === 'block_id'),
        ));
    }

    public static function block(string $type): ?Block
    {
        foreach (static::blocks() as $block) {
            if ($block->getName() === $type) {
                return $block;
            }
        }

        return null;
    }

    protected static function richEditor(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'link'],
                ['h2', 'h3'],
                ['bulletList', 'orderedList', 'blockquote'],
                ['undo', 'redo'],
            ]);
    }

    /**
     * Editor with everything TipTap offers in Filament: images, tables, columns, colors, alignment, collapsible sections.
     */
    public static function fullRichEditor(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                ['textColor', 'highlight', 'clearFormatting'],
                [ToolbarButtonGroup::make('Absatz', ['paragraph', 'h2', 'h3', 'h4', 'lead', 'small'])->textualButtons()],
                [ToolbarButtonGroup::make('Ausrichtung', ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'])],
                ['bulletList', 'orderedList', 'blockquote', 'details', 'horizontalRule'],
                ['attachFiles', 'table', 'grid'],
                ['undo', 'redo'],
            ])
            ->floatingToolbars([
                'table' => [
                    'tableAddColumnBefore', 'tableAddColumnAfter', 'tableDeleteColumn',
                    'tableAddRowBefore', 'tableAddRowAfter', 'tableDeleteRow',
                    'tableMergeCells', 'tableSplitCell',
                    'tableToggleHeaderRow', 'tableToggleHeaderCell',
                    'tableDelete',
                ],
                'grid' => [
                    'gridAddColumnBefore', 'gridAddColumnAfter', 'gridDeleteColumn', 'gridDelete',
                ],
            ])
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsDirectory('homepage')
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp'])
            ->fileAttachmentsMaxSize(4096);
    }

    /**
     * Optional jump mark, so that a block can be linked directly, e.g. /ueber-uns#team.
     */
    protected static function anchor(string $default): TextInput
    {
        return TextInput::make('anchor')
            ->label('Anker für Links')
            ->helperText(fn (?string $state): string => 'Damit lässt sich der Block direkt verlinken: …/bereich#'.($state ?: $default))
            ->prefix('#')
            ->default($default)
            ->alphaDash()
            ->maxLength(60);
    }

    /**
     * Alternative text for an image, required as soon as the image is set.
     */
    public static function altText(string $name, string $imageField): TextInput
    {
        return TextInput::make($name)
            ->label('Alternativtext')
            ->helperText('Beschreibt das Bild für Screenreader und Suchmaschinen, z. B. „Kinder löten an einer Platine“.')
            ->requiredWith($imageField)
            ->validationMessages(['required_with' => 'Bitte einen Alternativtext für das Bild angeben.'])
            ->maxLength(255);
    }

    public static function imageUpload(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->imageEditor()
            ->disk('public')
            ->directory('homepage')
            ->visibility('public')
            ->maxSize(4096);
    }
}
