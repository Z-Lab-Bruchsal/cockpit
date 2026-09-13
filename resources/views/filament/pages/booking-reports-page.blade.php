<x-filament-panels::page>
    {{ $this->form }}

    <div class="flex flex-wrap items-center gap-2">
        {{ $this->generateMonthlyReportAction }}
        {{ $this->generateYearlyReportAction }}
        @if ($this->canViewSupervisorReport())
            {{ $this->generateSupervisorMonthlyReportAction }}
            {{ $this->generateSupervisorYearlyReportAction }}
        @endif
    </div>
</x-filament-panels::page>
