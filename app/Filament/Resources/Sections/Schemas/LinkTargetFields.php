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
     * @return list<Select|TextInput>
     */
    public static function make(string $default = LinkTarget::SECTION): array
    {
        return [
            Select::make('target_type')
                ->label('Verlinkt auf')
                ->options(LinkTarget::options())
                ->default($default)
                ->selectablePlaceholder(false)
                ->live(),
            Select::make('section_id')
                ->label('Bereich')
                ->options(fn (): array => static::sectionOptions())
                ->searchable()
                ->required(fn (Get $get): bool => $get('target_type') === LinkTarget::SECTION)
                ->visible(fn (Get $get): bool => $get('target_type') === LinkTarget::SECTION),
            TextInput::make('url')
                ->label('Adresse')
                ->helperText('z. B. /kurse, #kontakt, https://… oder mailto:…')
                ->maxLength(500)
                ->required(fn (Get $get): bool => $get('target_type') === LinkTarget::URL)
                ->visible(fn (Get $get): bool => $get('target_type') === LinkTarget::URL),
            TextInput::make('anchor')
                ->label('Sprungmarke')
                ->helperText('Optional, z. B. „team“ für …/ueber-uns#team')
                ->prefix('#')
                ->alphaDash()
                ->maxLength(60)
                ->visible(fn (Get $get): bool => in_array($get('target_type'), [LinkTarget::SECTION, LinkTarget::COURSES, LinkTarget::NEWS, LinkTarget::CALENDAR], true)),
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
