<?php

namespace App\Filament\Resources\CourseTemplates\Schemas;

use App\Enums\CourseCardStyle;
use App\Enums\CourseDetailLayout;
use App\Filament\Resources\Sections\Schemas\ContentBlocks;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vorlage')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100),
                        Toggle::make('is_default')
                            ->label('Standardvorlage')
                            ->helperText('Gilt für alle Kurse ohne eigene Vorlage.'),
                        ColorPicker::make('accent_color')
                            ->label('Akzentfarbe')
                            ->helperText('Leer = Hauptfarbe der Homepage')
                            ->hex()
                            ->regex('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Visitenkarte')
                    ->description('Kleine Kachel in Kurslisten.')
                    ->schema([
                        Select::make('card_style')
                            ->label('Darstellung')
                            ->options(CourseCardStyle::class)
                            ->default(CourseCardStyle::ImageTop)
                            ->required(),
                        ContentBlocks::imageUpload('default_card_image_path')
                            ->label('Ersatzbild')
                            ->helperText('Wenn der Kurs kein eigenes Bild hat.'),
                        ContentBlocks::altText('default_card_image_alt', 'default_card_image_path'),
                    ])
                    ->columns(2),
                Section::make('Detailseite')
                    ->description('Seite hinter „Mehr Infos“.')
                    ->schema([
                        Select::make('detail_layout')
                            ->label('Darstellung')
                            ->options(CourseDetailLayout::class)
                            ->default(CourseDetailLayout::HeroImage)
                            ->required(),
                        ContentBlocks::imageUpload('default_image_path')
                            ->label('Ersatzbild'),
                        ContentBlocks::altText('default_image_alt', 'default_image_path'),
                    ])
                    ->columns(2),
                Section::make('Angezeigte Infos')
                    ->schema([
                        Toggle::make('show_age_range')
                            ->label('Altersangabe')
                            ->default(true),
                        Toggle::make('show_interests')
                            ->label('Interessen')
                            ->default(true),
                        Toggle::make('show_free_seats')
                            ->label('Freie Plätze')
                            ->default(true),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Zusätzlicher Inhalt')
                    ->description('Wird auf der Detailseite jedes Kurses mit dieser Vorlage angehängt, z. B. „Was mitbringen?“.')
                    ->schema([
                        ContentBlocks::make('extra_content'),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }
}
