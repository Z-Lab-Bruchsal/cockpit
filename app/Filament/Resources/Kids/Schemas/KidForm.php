<?php

namespace App\Filament\Resources\Kids\Schemas;

use App\Models\Kid;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class KidForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...static::fields(),
                Select::make('contacts')
                    ->label('Eltern / Kontakte')
                    ->relationship('contacts', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                TextInput::make('email')
                    ->label('Eigene E-Mail-Adresse')
                    ->email(),
                Textarea::make('notes')
                    ->label('Notizen')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The cockpit only shows the age. The exact birthdate stays visible to the parents only;
     * entering an age here stores an estimated birthdate.
     */
    public static function ageInput(): TextInput
    {
        return TextInput::make('age')
            ->label('Alter')
            ->numeric()
            ->integer()
            ->minValue(0)
            ->maxValue(25)
            ->suffix('Jahre')
            ->afterStateHydrated(fn (TextInput $component, ?Kid $record) => $component->state($record?->age()))
            ->helperText(fn (?Kid $record): ?string => match (true) {
                $record?->birthdate === null => null,
                $record->birthdate_estimated => 'Geschätzt – die Eltern können das genaue Geburtsdatum im Konto eintragen.',
                default => 'Von den Eltern angegeben. Eine Änderung ersetzt das Geburtsdatum durch einen Schätzwert.',
            })
            ->dehydrated(false)
            ->saveRelationshipsUsing(function (Kid $record, mixed $state): void {
                if (blank($state) || (int) $state === $record->age()) {
                    return;
                }

                $record->estimateBirthdateFromAge((int) $state)->save();
            });
    }

    /**
     * Fields shared with the children relation manager of contacts.
     *
     * @return array<Component|Field>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255),
            static::ageInput(),
            Select::make('interests')
                ->label('Interessen')
                ->relationship('interests', 'name', fn ($query) => $query->orderBy('sort'))
                ->multiple()
                ->preload(),
        ];
    }
}
