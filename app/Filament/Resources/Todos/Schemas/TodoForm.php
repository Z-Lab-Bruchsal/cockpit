<?php

namespace App\Filament\Resources\Todos\Schemas;

use App\Filament\Forms\RecipientSelect;
use App\Models\Todo;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                RecipientSelect::make('recipients')
                    ->label('zugeordnet'),
                Section::make()->components([
                    DatePicker::make('due_date')->label('Todo-Datum'),
                    Toggle::make('review')->label('Review')->hint('Todo geht nach Erledigung noch mal zum Ersteller'),
                    Toggle::make('individual_completion')
                        ->label('Jeder erledigt für sich')
                        ->helperText('Bei mehreren Zugeordneten muss jeder die Todo selbst erledigen. Sonst ist sie für alle erledigt, sobald einer sie erledigt.')
                        ->live()
                        ->disabled(fn (?Todo $record): bool => $record !== null && $record->user_id !== filament()->auth()->id()),
                ]),
                DatePicker::make('follow_up')->label('Wiedervorlage/Erinnerung')->hiddenOn(['create']),
                DatePicker::make('done_date')->label('Erledigt am')->hiddenOn(['create'])
                    ->hidden(fn (Get $get): bool => (bool) $get('individual_completion')),
                TextEntry::make('completion_status')
                    ->label('Erledigt von')
                    ->hiddenOn(['create'])
                    ->visible(fn (Get $get): bool => (bool) $get('individual_completion'))
                    ->state(fn (?Todo $record): string => $record === null ? '' : $record->assignees()
                        ->map(fn (User $user): string => ($record->isDoneFor($user) ? '✓ ' : '○ ').$user->name)
                        ->join(', ')),
            ]);
    }
}
