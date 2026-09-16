<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Models\Booking;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

class BookingsTable
{
    /**
     * @var array<int, string>
     */
    private const PERIODS = ['today', 'yesterday', 'this_week', 'this_month'];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('start_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Benutzer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_at')
                    ->label('Von')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end_at')
                    ->label('Bis')
                    ->dateTime()
                    ->placeholder('läuft noch')
                    ->sortable(),
                TextColumn::make('worked_minutes')
                    ->label('Gearbeitet')
                    ->numeric()
                    ->suffix(' min')
                    ->placeholder('—')
                    ->sortable()
                    ->summarize(Sum::make()->label('Gesamt')->suffix(' min')->query(fn (QueryBuilder $query) => $query->where('worked_minutes', '>', 0))),
                TextColumn::make('note')
                    ->label('Notiz')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('recordedBy.name')
                    ->label('Erfasst von')
                    ->toggleable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('start_at')
                    ->label('Zeitraum')
                    ->getKeyFromRecordUsing(fn (Booking $record): string => self::periodKeyFor($record->start_at))
                    ->getTitleFromRecordUsing(fn (Booking $record): string => self::periodLabel(self::periodKeyFor($record->start_at)))
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('start_at', $direction))
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Benutzer')
                    ->options(function () {
                        if (! User::find(filament()->auth()->user()->id)->can('Worktimes:ViewForeign')) {
                            return User::where('id', filament()->auth()->user()->id)->pluck('name', 'id');
                        } else {
                            return User::all()->pluck('name', 'id');
                        }
                    })
                    ->default(filament()->auth()->user()->id),
                Filter::make('period')
                    ->label('Zeitraum')
                    ->schema([
                        Select::make('value')
                            ->label('Zeitraum')
                            ->options([
                                'today' => 'Heute',
                                'yesterday' => 'Gestern',
                                'this_week' => 'Diese Woche',
                                'this_month' => 'Diesen Monat',
                            ])
                            ->default('this_week')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null) || ! in_array($data['value'], self::PERIODS, true)) {
                            return $query;
                        }

                        [$start, $end] = self::periodRangeUtc($data['value']);

                        return $query->whereBetween('start_at', [$start, $end]);
                    })
                    ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null)
                        ? 'Zeitraum: '.self::periodLabel($data['value'])
                        : null),
            ])->filtersLayout(FiltersLayout::AfterContent)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function periodRangeUtc(string $period): array
    {
        $timezone = config('app.business_timezone');
        $now = Carbon::now($timezone);

        [$start, $end] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };

        return [$start->clone()->setTimezone('UTC'), $end->clone()->setTimezone('UTC')];
    }

    private static function periodKeyFor(Carbon $startAt): string
    {
        $timezone = config('app.business_timezone');
        $local = $startAt->copy()->setTimezone($timezone);
        $now = Carbon::now($timezone);

        if ($local->isSameDay($now)) {
            return 'today';
        }

        if ($local->isSameDay($now->copy()->subDay())) {
            return 'yesterday';
        }

        if ($local->between($now->copy()->startOfWeek(), $now->copy()->endOfWeek())) {
            return 'this_week';
        }

        if ($local->between($now->copy()->startOfMonth(), $now->copy()->endOfMonth())) {
            return 'this_month';
        }

        return 'older';
    }

    private static function periodLabel(string $key): string
    {
        return match ($key) {
            'today' => 'Heute',
            'yesterday' => 'Gestern',
            'this_week' => 'Diese Woche',
            'this_month' => 'Diesen Monat',
            default => 'Älter',
        };
    }
}
