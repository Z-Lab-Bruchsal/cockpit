<?php

namespace App\Filament\Resources\TimeEntries\Widgets;

use App\Models\Group;
use App\Models\Todo;
use App\Models\User;
use App\Services\WorkTime\WorkTimeCalculator;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkTimeCalendarWidget extends CalendarWidget
{
    use InteractsWithPageFilters;

    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        // TODO: Filter noch auf nur eigene Times, wenn keine Permissions für Todos und andere User
        // Evtl. auch feinerer Filter auf die Objekte, die gesehen werden dürfen
        $eventType = $this->pageFilters['eventType'] ?? 'times';

        if(User::find(filament()->auth()->user()->id)->can('Worktimes:ViewForeign')) {
            $userIds = User::all()->pluck('id')->toArray();
        }
        else $userIds = [filament()->auth()->user()->id];
        
        $events = collect();

        if (in_array($eventType, ['both', 'times'], true)) {
            $events = $events->merge($this->timeEntryEvents($userIds, $info));
        }

        if (in_array($eventType, ['both', 'todos'], true)) {
            $events = $events->merge($this->todoEvents($userIds, $info));
        }

        return $events;
    }

    /**
     * @param  array<int, int>  $userIds
     * @return Collection<int, CalendarEvent>
     */
    private function timeEntryEvents(array $userIds, FetchInfo $info): Collection
    {
        $calculator = app(WorkTimeCalculator::class);
        $events = collect();

        $users = User::query()->whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $day = Carbon::parse($info->start)->startOfDay();
            $end = Carbon::parse($info->end);

            while ($day->lte($end)) {
                $sessions = $calculator->sessionsForDay($user, $day);

                foreach ($sessions['work'] as $segment) {
                    $events->push(
                        CalendarEvent::make()
                            ->title("{$user->name}: Arbeit {$segment['start']->format('H:i')}–{$segment['end']->format('H:i')}")
                            ->start($segment['start'])
                            ->end($segment['end'])
                            ->backgroundColor('#22c55e')
                            ->textColor('#ffffff'),
                    );
                }

                foreach ($sessions['break'] as $segment) {
                    $events->push(
                        CalendarEvent::make()
                            ->title("{$user->name}: Pause {$segment['start']->format('H:i')}–{$segment['end']->format('H:i')}")
                            ->start($segment['start'])
                            ->end($segment['end'])
                            ->backgroundColor('#f59e0b')
                            ->textColor('#ffffff'),
                    );
                }

                $day->addDay();
            }
        }

        return $events;
    }

    /**
     * @param  array<int, int>  $userIds
     * @return Collection<int, CalendarEvent>
     */
    private function todoEvents(array $userIds, FetchInfo $info): Collection
    {
        $groupIds = Group::query()
            ->whereHas('users', fn (Builder $query) => $query->whereIn('users.id', $userIds))
            ->pluck('id');

        $start = Carbon::parse($info->start);
        $end = Carbon::parse($info->end);

        $todos = Todo::query()
            ->whereNull('done_date')
            ->where(fn (Builder $query) => $query
                ->whereIn('user_id', $userIds)
                ->orWhere(fn (Builder $q) => $q->where('todoable_type', User::class)->whereIn('todoable_id', $userIds))
                ->orWhere(fn (Builder $q) => $q->where('todoable_type', Group::class)->whereIn('todoable_id', $groupIds)))
            ->where(fn (Builder $query) => $query
                ->whereBetween('due_date', [$start, $end])
                ->orWhereBetween('follow_up', [$start, $end]))
            ->get();

        $events = collect();

        foreach ($todos as $todo) {
            if ($todo->due_date) {
                $events->push(
                    CalendarEvent::make($todo)
                        ->title("Fällig: {$todo->name}")
                        ->start($todo->due_date)
                        ->end($todo->due_date)
                        ->allDay()
                        ->backgroundColor('#ef4444')
                        ->textColor('#ffffff'),
                );
            }

            if ($todo->follow_up) {
                $events->push(
                    CalendarEvent::make()
                        ->title("Wiedervorlage: {$todo->name}")
                        ->start($todo->follow_up)
                        ->end($todo->follow_up)
                        ->allDay()
                        ->backgroundColor('#3b82f6')
                        ->textColor('#ffffff'),
                );
            }
        }

        return $events;
    }
}
