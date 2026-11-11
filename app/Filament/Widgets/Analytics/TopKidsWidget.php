<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Resources\Kids\KidResource;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use App\Models\Kid;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopKidsWidget extends TableWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Top-Kinder';
    }

    public function table(Table $table): Table
    {
        $canEditKids = (bool) User::find(filament()->auth()->id())?->can('viewAny', Kid::class);

        return $table
            ->heading('Top-Kinder')
            ->description('Kinder mit den meisten Kursen im Zeitraum')
            ->records(fn (): array => collect($this->analytics()->topKids($this->analyticsFilters()))->keyBy('key')->all())
            ->paginated(false)
            ->emptyStateHeading('Keine Teilnahmen im Zeitraum')
            ->columns([
                TextColumn::make('name')
                    ->label('Kind')
                    ->weight('medium')
                    ->url(fn (array $record): ?string => $canEditKids && $record['kid_id'] !== null ? KidResource::getUrl('edit', ['record' => $record['kid_id']]) : null),
                TextColumn::make('courses')
                    ->label('Kurse')
                    ->numeric()
                    ->badge()
                    ->color('primary'),
                TextColumn::make('age')
                    ->label('Alter')
                    ->placeholder('–'),
                TextColumn::make('last_course')
                    ->label('Zuletzt')
                    ->wrap(),
            ]);
    }
}
