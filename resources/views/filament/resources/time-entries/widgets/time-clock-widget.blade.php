<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Zeiterfassung
        </x-slot>

        @php($state = $this->getState())
        @php($workedMinutes = $this->getWorkedMinutesToday())

        <div class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-500 dark:text-gray-400">
                Status: {{ match ($state) {
                    \App\Enums\TimeClockState::NotClockedIn => 'Nicht eingestempelt',
                    \App\Enums\TimeClockState::Working => 'Am Arbeiten',
                    \App\Enums\TimeClockState::OnBreak => 'In der Pause',
                    \App\Enums\TimeClockState::ClockedOut => 'Ausgestempelt',
                } }}
                &middot; Heute gearbeitet: {{ intdiv($workedMinutes, 60) }}h {{ $workedMinutes % 60 }}min
            </span>

            <div class="flex gap-2">
                {{ $this->clockInAction }}
                {{ $this->startBreakAction }}
                {{ $this->endBreakAction }}
                {{ $this->clockOutAction }}
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
