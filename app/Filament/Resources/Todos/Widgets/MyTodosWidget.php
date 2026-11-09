<?php

namespace App\Filament\Resources\Todos\Widgets;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Resources\Todos\Tables\TodosTable;
use App\Models\Todo;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Override;

class MyTodosWidget extends TableWidget implements HasDashboardLabel
{
    protected int|string|array $columnSpan = 2;

    public static function getDashboardLabel(): string
    {
        return 'Todos';
    }

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
