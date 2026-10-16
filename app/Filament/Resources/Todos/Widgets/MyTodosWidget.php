<?php

namespace App\Filament\Resources\Todos\Widgets;

use App\Filament\Resources\Todos\Tables\TodosTable;
use App\Models\Todo;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Override;

class MyTodosWidget extends TableWidget
{
    protected int|string|array $columnSpan = 2;

    #[Override]
    public static function canView(): bool
    {
        return parent::canView() && User::find(filament()->auth()->user()->id)->can('View:MyTodosWidget');
    }

    public function table(Table $table): Table
    {
        return TodosTable::configure($table)
            ->heading('Todos')
            ->query(
                Todo::query()
                    ->openFor(filament()->auth()->user())
                    ->relevantFor(filament()->auth()->user())
            );
    }
}
