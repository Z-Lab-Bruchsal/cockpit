<?php

namespace App\Filament\Resources\CourseInstances\RelationManagers;

use App\Enums\EnrollmentStatus;
use App\Models\Attendance;
use App\Models\CourseDate;
use App\Models\CourseInstance;
use App\Models\Enrollment;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Attendance grid: one row per confirmed child, one toggle column per course date.
 */
class AttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Anwesenheit';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var CourseInstance $ownerRecord */
        return $ownerRecord->dates()->exists();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Attendances have no policy of their own; recording them means changing the course instance.
     */
    protected function canRecordAttendance(): bool
    {
        return auth()->user()?->can('update', $this->getOwnerRecord()) ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var CourseInstance $instance */
        $instance = $this->getOwnerRecord();
        $dates = $instance->dates()->get();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('status', EnrollmentStatus::Confirmed)
                ->with(['kid', 'attendances']))
            ->paginated(false)
            ->columns([
                TextColumn::make('kid_name')
                    ->label('Kind')
                    ->state(fn (Enrollment $record): string => $record->displayKidName()),
                ...$dates->map(fn (CourseDate $date): ToggleColumn => ToggleColumn::make('attendance_'.$date->id)
                    ->label($date->starts_at->locale('de')->isoFormat('dd DD.MM.'))
                    ->tooltip($date->formattedRange())
                    ->alignCenter()
                    ->toggleable(false)
                    ->disabled(fn (): bool => ! $this->canRecordAttendance())
                    ->state(fn (Enrollment $record): bool => (bool) $record->attendances->firstWhere('course_date_id', $date->id)?->attended)
                    ->updateStateUsing(function (Enrollment $record, bool $state) use ($date): bool {
                        $this->markAttendance($record, $date, $state);

                        return $state;
                    }))->all(),
                TextColumn::make('attended_count')
                    ->label('Anwesend')
                    ->state(fn (Enrollment $record): string => $record->attendances->where('attended', true)->count().' / '.$dates->count()),
            ])
            ->headerActions([
                Action::make('markAllPresent')
                    ->label('Alle anwesend')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->authorize(fn (): bool => $this->canRecordAttendance())
                    ->schema([
                        Select::make('course_date_id')
                            ->label('Termin')
                            ->options($dates->mapWithKeys(fn (CourseDate $date): array => [$date->id => $date->formattedRange()])->all())
                            ->default($dates->first(fn (CourseDate $date): bool => $date->starts_at->isToday())?->id ?? $dates->last(fn (CourseDate $date): bool => $date->starts_at->isPast())?->id)
                            ->required(),
                    ])
                    ->action(function (array $data) use ($instance): void {
                        $date = $instance->dates()->findOrFail($data['course_date_id']);

                        $instance->enrollments()
                            ->where('status', EnrollmentStatus::Confirmed)
                            ->get()
                            ->each(fn (Enrollment $enrollment) => $this->markAttendance($enrollment, $date, true));

                        Notification::make()->success()->title('Alle als anwesend markiert')->send();
                    }),
            ]);
    }

    protected function markAttendance(Enrollment $enrollment, CourseDate $date, bool $attended): void
    {
        Attendance::query()->updateOrCreate(
            ['enrollment_id' => $enrollment->id, 'course_date_id' => $date->id],
            ['attended' => $attended, 'checked_by' => auth()->id(), 'checked_at' => now()],
        );
    }
}
