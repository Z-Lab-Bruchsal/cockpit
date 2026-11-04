<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\TeamArea;
use App\Filament\Resources\Sections\Schemas\ContentBlocks;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fields of the public team profile shown on the homepage.
 * Team members edit the self-editable part on their profile page; visibility is up to the admins.
 */
class TeamProfileFields
{
    /**
     * @return array<Component>
     */
    public static function selfEditable(): array
    {
        return [
            ContentBlocks::imageUpload('team_photo_path')
                ->label('Foto')
                ->directory('team')
                ->avatar()
                ->circleCropper(),
            ContentBlocks::altText('team_photo_alt', 'team_photo_path'),
            TextInput::make('team_role')
                ->label('Funktion')
                ->helperText('z. B. „Jugendleiterin“ oder „Informatik und Robotik“')
                ->maxLength(100),
            Textarea::make('team_bio')
                ->label('Kurzvorstellung')
                ->helperText('2–4 Sätze: Was machst du im Z-LAB?')
                ->rows(4)
                ->maxLength(600)
                ->columnSpanFull(),
            Select::make('interests')
                ->label('Themen')
                ->relationship('interests', 'name', fn (Builder $query) => $query->active())
                ->multiple()
                ->preload(),
            Toggle::make('team_contactable')
                ->label('Über die Homepage kontaktierbar')
                ->helperText('Besucher können dir über ein Formular schreiben. Deine E-Mail-Adresse bleibt verborgen.')
                ->default(true),
        ];
    }

    /**
     * @return array<Component>
     */
    public static function adminOnly(): array
    {
        return [
            Toggle::make('team_visible')
                ->label('Auf der Homepage zeigen')
                ->live(),
            Select::make('team_area')
                ->label('Teambereich')
                ->options(TeamArea::class)
                ->required(fn (Get $get): bool => (bool) $get('team_visible')),
            TextInput::make('team_sort')
                ->label('Sortierung')
                ->numeric()
                ->minValue(0)
                ->default(0),
        ];
    }
}
