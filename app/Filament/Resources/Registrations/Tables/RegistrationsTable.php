<?php

namespace App\Filament\Resources\Registrations\Tables;

use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\Registrations\Actions\ApproveRegistrationAction;
use App\Filament\Resources\Registrations\Actions\ApproveSchoolRequestAction;
use App\Filament\Resources\Registrations\Actions\CancelRegistrationAction;
use App\Filament\Resources\Registrations\Actions\ConfirmRegistrationAction;
use App\Filament\Resources\Registrations\Actions\DeclineRegistrationAction;
use App\Filament\Resources\Registrations\Actions\DeclineSchoolRequestAction;
use App\Models\Enrollment;
use App\Models\Registration;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['courseInstance.course', 'courseInstance.firstDate', 'contact', 'school', 'enrollments.kid']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('courseInstance.course.name')
                    ->label('Kurs')
                    ->description(fn (Registration $record): ?string => $record->courseInstance->title)
                    ->searchable(),
                TextColumn::make('courseInstance.firstDate.starts_at')
                    ->label('Beginn')
                    ->dateTime(),
                TextColumn::make('contact_name')
                    ->label('Kontakt')
                    ->state(fn (Registration $record): string => $record->displayContactName())
                    ->description(fn (Registration $record): ?string => $record->school !== null ? $record->school->name : $record->phone)
                    ->searchable(['contact_name', 'email']),
                TextColumn::make('kids')
                    ->label('Kinder / Klasse')
                    ->state(fn (Registration $record): array => $record->activeEnrollments->map(fn (Enrollment $enrollment): string => $enrollment->displayKidName())->all())
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->description(fn (Registration $record): ?string => match (true) {
                        $record->awaitsWaitlistDecision() => 'Warteliste – Freigabe nötig',
                        $record->isOnWaitlist() => 'Warteliste',
                        default => null,
                    }),
                TextColumn::make('source')
                    ->label('Quelle')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Angelegt')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(RegistrationStatus::class)
                    ->multiple(),
                SelectFilter::make('course_instance_id')
                    ->label('Durchführung')
                    ->relationship('courseInstance', 'id', fn (Builder $query) => $query->with('course')->latest('id'))
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->displayTitle().' (#'.$record->id.')')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('source')
                    ->label('Quelle')
                    ->options(RegistrationSource::class),
            ])
            ->recordActions([
                ViewAction::make(),
                ApproveSchoolRequestAction::make(),
                DeclineSchoolRequestAction::make(),
                ConfirmRegistrationAction::make(),
                ApproveRegistrationAction::make(),
                DeclineRegistrationAction::make(),
                CancelRegistrationAction::make(),
            ]);
    }
}
