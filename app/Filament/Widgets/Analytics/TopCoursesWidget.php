<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use App\Models\Course;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;

class TopCoursesWidget extends TableWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Top-Kurse';
    }

    public function table(Table $table): Table
    {
        $canEditCourses = (bool) User::find(filament()->auth()->id())?->can('viewAny', Course::class);

        return $table
            ->heading('Top-Kurse')
            ->description('Kurse mit den meisten Anmeldungen im Zeitraum')
            ->records(fn (): array => collect($this->analytics()->topCourses($this->analyticsFilters()))->keyBy('course_id')->all())
            ->paginated(false)
            ->emptyStateHeading('Keine Durchführungen im Zeitraum')
            ->columns([
                TextColumn::make('name')
                    ->label('Kurs')
                    ->weight('medium')
                    ->wrap()
                    ->url(fn (array $record): ?string => $canEditCourses ? CourseResource::getUrl('edit', ['record' => $record['course_id']]) : null),
                TextColumn::make('instances')
                    ->label('Durchf.')
                    ->numeric(),
                TextColumn::make('participants')
                    ->label('Anmeldungen')
                    ->numeric()
                    ->badge()
                    ->color('primary'),
                TextColumn::make('waitlisted')
                    ->label('Warteliste')
                    ->numeric(),
                TextColumn::make('utilization')
                    ->label('Auslastung')
                    ->formatStateUsing(fn (?float $state): string => Number::format($state, precision: 1, locale: 'de').' %')
                    ->placeholder('–'),
            ]);
    }
}
