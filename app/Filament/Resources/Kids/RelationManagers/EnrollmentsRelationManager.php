<?php

namespace App\Filament\Resources\Kids\RelationManagers;

use App\Filament\Resources\CourseInstances\CourseInstanceResource;
use App\Models\Enrollment;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Course history of a child, including how often it was actually there.
 */
class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Kurse';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['courseInstance.course', 'courseInstance.firstDate', 'courseInstance.dates', 'attendances']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('courseInstance.course.name')
                    ->label('Kurs')
                    ->description(fn (Enrollment $record): ?string => $record->courseInstance->title),
                TextColumn::make('courseInstance.firstDate.starts_at')
                    ->label('Beginn')
                    ->date(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('attendance')
                    ->label('Anwesend')
                    ->state(fn (Enrollment $record): string => $record->attendances->where('attended', true)->count().' / '.$record->courseInstance->dates->count()),
            ])
            ->recordActions([
                Action::make('openInstance')
                    ->label('Durchführung öffnen')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->url(fn (Enrollment $record): string => CourseInstanceResource::getUrl('edit', ['record' => $record->course_instance_id])),
            ]);
    }
}
