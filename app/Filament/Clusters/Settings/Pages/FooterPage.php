<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Resources\Sections\Schemas\ContentBlocks;
use App\Models\CompanySetting;
use App\Models\Section as SectionModel;
use BackedEnum;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class FooterPage extends CompanySettingsSubPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Homepage';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Footer';

    protected static ?string $title = 'Footer';

    protected static ?string $slug = 'footer';

    protected function settingsComponents(): array
    {
        return [
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
                ->defaultItems(0)
                ->columnSpanFull(),
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
                ->defaultItems(0)
                ->columnSpanFull(),
        ];
    }

    protected function formColumns(): int
    {
        return 1;
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
}
