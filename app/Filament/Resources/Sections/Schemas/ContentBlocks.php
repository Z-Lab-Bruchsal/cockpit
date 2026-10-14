<?php

namespace App\Filament\Resources\Sections\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

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
            Block::make('text')
                ->label('Text')
                ->icon(Heroicon::OutlinedBars3BottomLeft)
                ->schema([
                    static::richEditor('body')
                        ->label('Text')
                        ->required(),
                ]),
            Block::make('image')
                ->label('Bild')
                ->icon(Heroicon::OutlinedPhoto)
                ->schema([
                    static::imageUpload('image')
                        ->label('Bild')
                        ->required(),
                    TextInput::make('alt')
                        ->label('Alternativtext')
                        ->maxLength(255),
                    TextInput::make('caption')
                        ->label('Bildunterschrift')
                        ->maxLength(255),
                ]),
            Block::make('text_image')
                ->label('Text mit Bild')
                ->icon(Heroicon::OutlinedNewspaper)
                ->schema([
                    static::richEditor('body')
                        ->label('Text')
                        ->required()
                        ->columnSpanFull(),
                    static::imageUpload('image')
                        ->label('Bild')
                        ->required(),
                    TextInput::make('alt')
                        ->label('Alternativtext')
                        ->maxLength(255),
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
            Block::make('contact')
                ->label('Kontakt / Adresse')
                ->icon(Heroicon::OutlinedMapPin)
                ->schema([
                    static::richEditor('intro')
                        ->label('Einleitung'),
                ]),
        ];
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
