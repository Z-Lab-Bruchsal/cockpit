<?php

namespace App\Filament\Resources\NewsPosts\Schemas;

use App\Filament\Resources\Sections\Schemas\ContentBlocks;
use App\Models\NewsPost;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class NewsPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaSection::make('Beitrag')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titel')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?NewsPost $record): void {
                                if ($record === null && blank($get('slug'))) {
                                    $set('slug', Str::slug($state ?? '', language: 'de'));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Kurzname (URL)')
                            ->helperText('Adresse des Beitrags, z. B. /news/drone-soccer-cup')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        DateTimePicker::make('published_at')
                            ->label('Veröffentlichen am')
                            ->helperText('Leer lassen = Entwurf. Ein Datum in der Zukunft plant den Beitrag.')
                            ->seconds(false),
                        Textarea::make('teaser')
                            ->label('Kurztext')
                            ->helperText('Erscheint in der News-Übersicht und im RSS-Feed.')
                            ->rows(3)
                            ->maxLength(300)
                            ->columnSpanFull(),
                        ContentBlocks::imageUpload('image_path')
                            ->label('Vorschaubild'),
                        ContentBlocks::altText('image_alt', 'image_path'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                SchemaSection::make('Inhalt')
                    ->schema([
                        ContentBlocks::make(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
