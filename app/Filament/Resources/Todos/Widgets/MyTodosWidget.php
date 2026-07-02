<?php

namespace App\Filament\Resources\Todos\Widgets;

use App\Filament\Actions\DoneTodoAction;
use App\Filament\Actions\ReopenTodoAction;
use App\Filament\Resources\Todos\Tables\TodosTable;
use App\Filament\Resources\Todos\TodoResource;
use App\Models\Group;
use App\Models\Todo;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MyTodosWidget extends TableWidget
{
    protected int|string|array $columnSpan = 2;

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
