<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\CourseInstances\CourseInstanceResource;
use App\Models\CourseInstanceNote;
use App\Models\Enrollment;
use App\Models\Registration;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistrationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Anmeldung')
                    ->schema([
                        TextEntry::make('courseInstance.course.name')
                            ->label('Kurs')
                            ->url(fn (Registration $record): string => CourseInstanceResource::getUrl('edit', ['record' => $record->course_instance_id])),
                        TextEntry::make('courseInstance.firstDate.starts_at')
                            ->label('Beginn')
                            ->dateTime(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('source')
                            ->label('Quelle')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label('Angelegt')
                            ->dateTime(),
                        TextEntry::make('confirmed_at')
                            ->label('Bestätigt')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('approval')
                            ->label('Freigabe')
                            ->state(fn (Registration $record): ?string => match (true) {
                                $record->awaitsSiblingApproval() => 'Geschwister über die freien Plätze hinaus – Entscheidung nötig',
                                $record->awaitsWaitlistDecision() => 'Familie auf der Warteliste ist größer als die freien Plätze – Entscheidung nötig',
                                $record->approval_decided_at !== null => 'Entschieden am '.$record->approval_decided_at->format('d.m.Y H:i'),
                                default => null,
                            })
                            ->badge()
                            ->color(fn (Registration $record): string => $record->approval_decided_at === null ? 'warning' : 'gray')
                            ->visible(fn (Registration $record): bool => $record->needs_approval || $record->approval_requested_at !== null || $record->approval_decided_at !== null)
                            ->columnSpanFull(),
                        TextEntry::make('expires_at')
                            ->label('Verfällt')
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Kontakt')
                    ->schema([
                        TextEntry::make('contact_name')
                            ->label('Name')
                            ->state(fn (Registration $record): string => $record->displayContactName())
                            ->url(fn (Registration $record): ?string => $record->contact_id ? ContactResource::getUrl('edit', ['record' => $record->contact_id]) : null),
                        TextEntry::make('email')
                            ->label('E-Mail')
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('phone')
                            ->label('Telefon')
                            ->placeholder('—'),
                        TextEntry::make('message')
                            ->label('Nachricht')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Schulklasse')
                    ->schema([
                        TextEntry::make('school.name')
                            ->label('Schule')
                            ->url(fn (Registration $record): ?string => $record->school_id ? ContactResource::getUrl('edit', ['record' => $record->school_id]) : null),
                        TextEntry::make('school_address')
                            ->label('Adresse der Schule')
                            ->state(fn (Registration $record): ?string => $record->school?->addressLine()),
                        TextEntry::make('school.email')
                            ->label('Funktionsadresse')
                            ->copyable(),
                        TextEntry::make('venue')
                            ->label('Ort')
                            ->badge(),
                        TextEntry::make('class_name')
                            ->label('Klasse')
                            ->state(fn (Registration $record): string => $record->schoolClass()['class_name']),
                        TextEntry::make('students')
                            ->label('Schüler*innen')
                            ->state(fn (Registration $record): int => $record->schoolClass()['students']),
                        TextEntry::make('teacher')
                            ->label('Lehrkraft')
                            ->state(fn (Registration $record): string => $record->schoolClass()['teacher_name']),
                        TextEntry::make('teacher_confirmed_at')
                            ->label('Teilnahme bestätigt')
                            ->dateTime()
                            ->placeholder('noch nicht'),
                        TextEntry::make('staff_remind_at')
                            ->label('Erinnerung an Kursverantwortliche*n')
                            ->dateTime()
                            ->placeholder('—')
                            ->helperText(fn (Registration $record): ?string => $record->staff_reminded_at ? 'verschickt am '.$record->staff_reminded_at->format('d.m.Y H:i') : null),
                        TextEntry::make('replacesRegistration.courseInstance.firstDate.starts_at')
                            ->label('Verschiebung von')
                            ->dateTime()
                            ->visible(fn (Registration $record): bool => $record->replaces_registration_id !== null),
                        TextEntry::make('teacher_remark')
                            ->label('Anmerkung der Lehrkraft')
                            ->state(fn (Registration $record): ?string => $record->school_class_data['teacher_remark'] ?? null)
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('decision_note')
                            ->label('Nachricht bei der Entscheidung')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->visible(fn (Registration $record): bool => $record->isSchoolBooking())
                    ->columnSpanFull(),
                Section::make('Notizen zur Durchführung')
                    ->schema([
                        RepeatableEntry::make('courseInstance.internalNotes')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('body')
                                    ->hiddenLabel()
                                    ->columnSpan(3),
                                TextEntry::make('created_at')
                                    ->hiddenLabel()
                                    ->dateTime()
                                    ->helperText(fn (CourseInstanceNote $record): ?string => $record->user?->name),
                            ])
                            ->columns(4)
                            ->placeholder('Noch keine Notizen.'),
                    ])
                    ->visible(fn (Registration $record): bool => $record->isSchoolBooking())
                    ->columnSpanFull(),
                Section::make('Kinder')
                    ->visible(fn (Registration $record): bool => ! $record->isSchoolBooking())
                    ->schema([
                        RepeatableEntry::make('enrollments')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('kid_name')
                                    ->label('Name')
                                    ->state(fn (Enrollment $record): string => $record->displayKidName()),
                                TextEntry::make('age')
                                    ->label('Alter')
                                    ->state(fn (Enrollment $record): ?string => $record->kidAge() !== null ? $record->kidAge().' Jahre' : null)
                                    ->placeholder('—'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge(),
                                TextEntry::make('waitlist_position')
                                    ->label('Wartelistenplatz')
                                    ->placeholder('—'),
                            ])
                            ->columns(4),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
