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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Guava\Calendar\Enums\CalendarViewType;
use UnitEnum;

class WorkTimeCalendarPage extends Page
{
    use HasFiltersForm {
        updatedFilters as protected baseUpdatedFilters;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Zeiterfassung';

    protected static ?string $navigationLabel = 'Kalender';

    protected static ?string $title = 'Kalender';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('calendarView')
                    ->label('Kalenderansicht')
                    ->options([
                        CalendarViewType::DayGridMonth->value => 'Monat',
                        CalendarViewType::DayGridWeek->value => 'Woche (Raster)',
                        CalendarViewType::TimeGridWeek->value => 'Woche (Zeitplan)',
                        CalendarViewType::DayGridDay->value => 'Tag (Raster)',
                        CalendarViewType::TimeGridDay->value => 'Tag (Zeitplan)',
                        CalendarViewType::ListWeek->value => 'Liste (Woche)',
                        CalendarViewType::ListMonth->value => 'Liste (Monat)',
                    ])
                    ->live()
                    ->default(CalendarViewType::DayGridMonth),
                Select::make('eventType')
                    ->label('Anzeigen')
                    ->options([
                        'todos' => 'Todos',
                        'times' => 'Zeiten',
                    ])
                    ->live()
                    ->default('todos')
                    ->preload()
                    ->visible(fn () => User::find(filament()->auth()->user()->id)->can('View:Todo')),
                Select::make('userIds')
                    ->label('Benutzer')
                    ->multiple()
                    ->options(function () {
                        return User::all()->pluck('name', 'id');
                    })
                    ->visible(function (Get $get) {
                        if (! User::find(filament()->auth()->user()->id)->can('Worktimes:ViewForeign') && $get('eventType') == 'times') {
                            return false;
                        } else {
                            return true;
                        }
                    }
                    )
                    ->default(filament()->auth()->user()->id)
                    ->preload()
                    ->placeholder('Alle sichtbaren Benutzer'),
            ]);
    }

    public function updatedFilters(mixed $value, ?string $key): void
    {
        $this->baseUpdatedFilters();

        if ($key === 'calendarView') {
            $this->dispatch('calendar--set', key: 'view', value: $value);

            return;
        }

        $this->dispatch('calendar--refresh');
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
