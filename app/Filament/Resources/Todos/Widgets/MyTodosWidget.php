<?php

namespace App\Filament\Resources\Todos\Widgets;

use App\Filament\Resources\Todos\Tables\TodosTable;
use App\Models\Group;
use App\Models\Todo;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Override;

class MyTodosWidget extends TableWidget
{
    protected int|string|array $columnSpan = 2;

    #[Override]
    public static function canView(): bool
    {
        return parent::canView() && User::find(filament()->auth()->user()->id)->can("View:MyTodosWidget");
    }

    public function table(Table $table): Table
    {
        return TodosTable::configure($table)
            ->heading('Todos')
            ->query(
                Todo::query()
                    ->whereNull('done_date')
                    ->where(function (Builder $query) {
                        $query
                            ->orWhere('user_id', filament()->auth()->user()->id)
                            ->orWhere('todoable_type', User::class)->where('todoable_id', filament()->auth()->user()->id)
                            ->orWhere('todoable_type', Group::class)->whereIn('todoable_id', User::find(filament()->auth()->user()->id)->groups()->get()->pluck('id'));
                    })
            );
    }
}
