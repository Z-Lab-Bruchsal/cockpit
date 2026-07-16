<?php

namespace App\Filament\Resources\TimeEntries\Widgets;

use App\Enums\TimeClockState;
use App\Services\WorkTime\TimeClockService;
use App\Services\WorkTime\WorkTimeCalculator;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use RuntimeException;

class TimeClockWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.resources.time-entries.widgets.time-clock-widget';

    protected int|string|array $columnSpan = 1;

    public function getState(): TimeClockState
    {
        return app(TimeClockService::class)->currentState(filament()->auth()->user());
    }

    public function getWorkedMinutesToday(): int
    {
        $today = Carbon::now(config('app.business_timezone'))->startOfDay();

        return app(WorkTimeCalculator::class)->workedMinutes(filament()->auth()->user(), $today);
    }

    public function clockInAction(): Action
    {
        return Action::make('clockIn')
            ->label('Kommen')
            ->color('success')
            ->visible(fn (): bool => $this->getState() === TimeClockState::NotClockedIn)
            ->action(fn () => $this->recordAndRefresh('clockIn'));
    }

    public function startBreakAction(): Action
    {
        return Action::make('startBreak')
            ->label('Pause')
            ->color('warning')
            ->visible(fn (): bool => $this->getState() === TimeClockState::Working)
            ->action(fn () => $this->recordAndRefresh('startBreak'));
    }

    public function endBreakAction(): Action
    {
        return Action::make('endBreak')
            ->label('Pause beenden')
            ->color('warning')
            ->visible(fn (): bool => $this->getState() === TimeClockState::OnBreak)
            ->action(fn () => $this->recordAndRefresh('endBreak'));
    }

    public function clockOutAction(): Action
    {
        return Action::make('clockOut')
            ->label('Gehen')
            ->color('danger')
            ->visible(fn (): bool => in_array($this->getState(), [TimeClockState::Working, TimeClockState::OnBreak], true))
            ->action(fn () => $this->recordAndRefresh('clockOut'));
    }

    private function recordAndRefresh(string $method): void
    {
        $user = filament()->auth()->user();

        try {
            app(TimeClockService::class)->{$method}($user, $user);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Aktion nicht möglich')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
