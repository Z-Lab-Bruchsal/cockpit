<?php

namespace App\Filament\Resources\Sections\Schemas;

use App\Models\Section;
use App\Services\Homepage\LinkTarget;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Fields for choosing where a tile or button links to, see LinkTarget.
 */
class LinkTargetFields
{
    /**
     * Field names as stored in tiles.
     *
     * @var array{type: string, section: string, url: string, anchor: string}
     */
    public const TILE_COLUMNS = ['type' => 'link_type', 'section' => 'link_section_id', 'url' => 'link_url', 'anchor' => 'link_anchor'];

    /**
     * @param  array{type: string, section: string, url: string, anchor: string}  $names  Field names, by default those of block data.
     * @return list<Select|TextInput>
     */
    public static function make(string $default = LinkTarget::SECTION, array $names = ['type' => 'target_type', 'section' => 'section_id', 'url' => 'url', 'anchor' => 'anchor']): array
    {
        $isType = fn (Get $get, string ...$types): bool => in_array($get($names['type']), $types, true);

        return [
            Select::make($names['type'])
                ->label('Verlinkt auf')
                ->options(LinkTarget::options())
                ->default($default)
                ->selectablePlaceholder(false)
                ->live(),
            Select::make($names['section'])
                ->label('Bereich')
                ->options(fn (): array => static::sectionOptions())
                ->searchable()
                ->required(fn (Get $get): bool => $isType($get, LinkTarget::SECTION))
                ->visible(fn (Get $get): bool => $isType($get, LinkTarget::SECTION)),
            TextInput::make($names['url'])
                ->label('Adresse')
                ->helperText('z. B. /kurse, #kontakt, https://… oder mailto:…')
                ->maxLength(500)
                ->required(fn (Get $get): bool => $isType($get, LinkTarget::URL))
                ->visible(fn (Get $get): bool => $isType($get, LinkTarget::URL)),
            TextInput::make($names['anchor'])
                ->label('Sprungmarke')
                ->helperText('Optional, z. B. „team“ für …/ueber-uns#team')
                ->prefix('#')
                ->alphaDash()
                ->maxLength(60)
                ->visible(fn (Get $get): bool => $isType($get, LinkTarget::SECTION, LinkTarget::COURSES, LinkTarget::NEWS, LinkTarget::CALENDAR)),
        ];
    }

    /**
     * All sections except the start page, labelled with their position in the tree.
     *
     * @return array<int, string>
     */
    public static function sectionOptions(): array
    {
        $sections = Section::orderByTree(Section::query()->where('is_home', false))->get(['id', 'parent_id', 'title']);
        $depths = [];

        return $sections
            ->mapWithKeys(function (Section $section) use (&$depths): array {
                $depths[$section->id] = $section->parent_id !== null ? ($depths[$section->parent_id] ?? 0) + 1 : 0;

                return [$section->id => str_repeat('— ', $depths[$section->id]).$section->title];
            })
            ->all();
    }
}
