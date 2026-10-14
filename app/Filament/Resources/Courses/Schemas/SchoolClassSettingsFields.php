<?php

namespace App\Filament\Resources\Courses\Schemas;

use App\Models\CompanySetting;
use App\Models\Course;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Reminder and deadline settings for school class courses. On courses and instances they are optional overrides:
 * empty = inherited from the course or the company settings.
 */
class SchoolClassSettingsFields
{
    /**
     * @param  Closure(string, Get): string  $inheritedLabel  e.g. fn (string $field, Get $get) => 'Standard: 14 Tage'
     * @return list<Select|TextInput>
     */
    public static function overrides(Closure $inheritedLabel): array
    {
        return [
            Select::make('school_reminders_enabled')
                ->label('Erinnerungsmails')
                ->options(['1' => 'Verschicken', '0' => 'Nicht verschicken'])
                ->placeholder(fn (Get $get): string => $inheritedLabel('school_reminders_enabled', $get))
                ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : ($state ? '1' : '0'))
                ->dehydrateStateUsing(fn (mixed $state): ?bool => $state === null || $state === '' ? null : (bool) $state),
            static::days('teacher_reminder_days', 'Erinnerung an die Lehrkraft')
                ->placeholder(fn (Get $get): string => $inheritedLabel('teacher_reminder_days', $get))
                ->helperText('Tage vor dem ersten Termin, mit Bitte um Bestätigung und Anfahrtsskizze'),
            static::days('staff_reminder_days', 'Erinnerung an Kursverantwortliche*n')
                ->placeholder(fn (Get $get): string => $inheritedLabel('staff_reminder_days', $get))
                ->helperText('Tage vor dem ersten Termin, mit allen Daten zur Schule'),
            static::days('school_reschedule_deadline_days', 'Stornieren/Verschieben möglich bis')
                ->placeholder(fn (Get $get): string => $inheritedLabel('school_reschedule_deadline_days', $get))
                ->helperText('Tage vor dem ersten Termin; danach nur noch telefonisch'),
        ];
    }

    public static function days(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->integer()
            ->minValue(0)
            ->maxValue(365)
            ->suffix('Tage');
    }

    /**
     * Value of a setting as inherited from the course (if given) or the company settings, formatted for placeholders.
     */
    public static function inheritedValue(?Course $course, string $field): string
    {
        $value = $course?->{$field} ?? CompanySetting::current()->{$field};

        return $field === 'school_reminders_enabled'
            ? ($value ? 'Verschicken' : 'Nicht verschicken')
            : $value.' Tage';
    }
}
