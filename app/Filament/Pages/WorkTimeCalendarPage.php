<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TimeEntries\Widgets\WorkTimeCalendarWidget;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class WorkTimeCalendarPage extends Page
{
    use HasFiltersForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Zeiterfassung';

    protected static ?string $navigationLabel = 'Kalender';

    protected static ?string $title = 'Kalender';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('eventType')
                    ->label('Anzeigen')
                    ->options(function () {
                        return ['both' => 'Zeiten & Todos',
                            'times' => 'Nur Zeiten',
                            'todos' => 'Nur Todos',
                        ];
                    })
                    ->hidden(fn () => ! User::find(filament()->auth()->user()->id)->can('todo:view')),
                Select::make('userId')
                    ->label('Benutzer')
                    ->options(function () {
                        return User::all()->pluck('name', 'id');
                    })
                    ->visible(fn () => User::find(filament()->auth()->user()->id)->can('Worktimes:ViewForeign'))
                    ->default(filament()->auth()->user()->id)
                    ->preload()
                    ->placeholder('Alle sichtbaren Benutzer'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedSchema::make('filtersForm'),
                Grid::make(1)
                    ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getWidgets())),
            ]);
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            WorkTimeCalendarWidget::class,
        ];
    }
}
