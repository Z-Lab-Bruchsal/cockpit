@php($state = $this->getState())
@php($workedMinutes = $this->getWorkedMinutesToday())
@php($currentSegmentMinutes = $this->getCurrentSegmentMinutes())

{{-- Single root element: Livewire reads the tag of a nested component from its first tag, an @if here would be a comment. --}}
<div class="contents">
    @if ($compact)
        <div wire:poll.60s class="flex items-center gap-2">
            <span class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                <span @class([
                    'size-2 rounded-full',
                    'bg-success-500' => $state === \App\Enums\TimeClockState::Working,
                    'bg-gray-300 dark:bg-gray-600' => $state === \App\Enums\TimeClockState::NotClockedIn,
                ])></span>
                Heute {{ intdiv($workedMinutes, 60) }}h {{ $workedMinutes % 60 }}min
            </span>

            @if ($this->clockInAction->isVisible())
                {{ $this->clockInAction }}
            @endif
            @if ($this->clockOutAction->isVisible())
                {{ $this->clockOutAction }}
            @endif
        </div>
    @else
        <x-filament-widgets::widget wire:poll.60s>
            <x-filament::section>
                <x-slot name="heading">
                    Zeiterfassung
                </x-slot>

                <div class="flex items-center justify-between gap-4">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Status: {{ match ($state) {
                            \App\Enums\TimeClockState::NotClockedIn => 'Nicht eingestempelt',
                            \App\Enums\TimeClockState::Working => 'Am Arbeiten',
                        } }}
                        &middot; Heute gearbeitet: {{ intdiv($workedMinutes, 60) }}h {{ $workedMinutes % 60 }}min
                        @if ($currentSegmentMinutes !== null)
                            (aktuelle Periode: {{ intdiv($currentSegmentMinutes, 60) }}h {{ $currentSegmentMinutes % 60 }}min)
                        @endif
                    </span>

                    <div class="flex gap-2">
                        @if ($this->clockInAction->isVisible())
                            {{ $this->clockInAction }}
                        @endif
                        @if ($this->clockOutAction->isVisible())
                            {{ $this->clockOutAction }}
                        @endif
                    </div>
                </div>
            </x-filament::section>
        </x-filament-widgets::widget>
    @endif
</div>
