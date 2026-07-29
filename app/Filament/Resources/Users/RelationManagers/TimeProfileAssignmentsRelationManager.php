<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\TimeProfileAssignment;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class TimeProfileAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'timeProfileAssignments';

    protected static ?string $title = 'Zeitmodelle';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('time_profile_id')
                    ->label('Zeitmodell')
                    ->relationship('timeProfile', 'name')
                    ->required(),
                DatePicker::make('effective_from')
                    ->label('Gültig ab')
                    ->required()
                    ->rules([
                        fn (Get $get, ?TimeProfileAssignment $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record) {
                            $overlaps = TimeProfileAssignment::overlapsExisting(
                                $this->getOwnerRecord()->id,
                                Carbon::parse($value),
                                filled($get('effective_to')) ? Carbon::parse($get('effective_to')) : null,
                                $record?->id,
                            );

                            if ($overlaps) {
                                $fail('Der Gültigkeitszeitraum überschneidet sich mit einem bestehenden Zeitmodell.');
                            }
                        },
                    ]),
                DatePicker::make('effective_to')
                    ->label('Gültig bis')
                    ->helperText('Leer lassen, wenn das Zeitmodell aktuell gültig ist.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('timeProfile.name')
                    ->label('Zeitmodell'),
                TextColumn::make('timeProfile.weekly_hours')
                    ->label('Wochenstunden')
                    ->suffix(' h'),
                TextColumn::make('effective_from')
                    ->label('Gültig ab')
                    ->date(),
                TextColumn::make('effective_to')
                    ->label('Gültig bis')
                    ->date()
                    ->placeholder('aktuell'),
            ])
            ->defaultSort('effective_from', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
