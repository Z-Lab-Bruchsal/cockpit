<?php

namespace App\Filament\Resources\Todos\Schemas;

use App\Models\Group;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TodoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Titel')
                    ->required(),
                Select::make('user.name')->relationship('user', 'name')->label('Benutzer')->hiddenOn(['create'])->disabledOn(['edit']),
                Hidden::make('user_id')->default(filament()->auth()->user()->id),
                RichEditor::make('content')
                    ->label('Todo')
                    ->columnSpanFull(),
                MorphToSelect::make('todoable')
                    ->types([
                        MorphToSelect\Type::make(User::class)->label('Benutzer')->titleAttribute('name'),
                        MorphToSelect\Type::make(Group::class)->label('Gruppe')->titleAttribute('name'),
                    ])
                    ->label('zugeordnet'),
                Section::make()->components([
                    DatePicker::make('due_date')->label('Todo-Datum'),
                    Toggle::make('review')->label('Review')->hint('Todo geht nach Erledigung noch mal zum Ersteller'),

                ]),
                // DatePicker::make('follow_up')->label('Wiedervorlage/Erinnerung'),
                DatePicker::make('done_date')->label('Erledigt am')->hiddenOn(['create']),
            ]);
    }
}
