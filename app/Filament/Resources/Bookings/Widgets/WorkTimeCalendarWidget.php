<?php

namespace App\Filament\Resources\Bookings\Widgets;

use App\Filament\Resources\Todos\TodoResource;
use App\Models\Booking;
use App\Models\Group;
use App\Models\Todo;
use App\Models\User;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkTimeCalendarWidget extends CalendarWidget
{
    use InteractsWithPageFilters;

    protected bool $eventClickEnabled = true;

    public function getCalendarView(): CalendarViewType
    {
        return CalendarViewType::tryFrom($this->pageFilters['calendarView'] ?? '') ?? CalendarViewType::DayGridMonth;
    }

    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        $eventType = $this->pageFilters['eventType'] ?? 'times';

        $events = collect();

        if ($eventType == 'times') {
            $events = $events->merge($this->bookingEvents($info));
        }

        if ($eventType == 'todos' && User::find(filament()->auth()->user()->id)->can('View:MyTodosWidget')) {
            $events = $events->merge($this->todoEvents($info));
        }

        return $events;
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    private function bookingEvents(FetchInfo $info): Collection
    {
        if (User::find(filament()->auth()->user()->id)->can('Worktimes:ViewForeign')) {
            $possibleUsers = User::all()->pluck('id')->toArray();
            if (count($this->pageFilters['userIds']) == 0) {
                $userIds = $possibleUsers;
            } else {
                $selectedUsers = $this->pageFilters['userIds'];
                $userIds = array_intersect($possibleUsers, $selectedUsers);
            }

        } else {
            $userIds = [filament()->auth()->user()->id];
        }

        $start = Carbon::parse($info->start);
        $end = Carbon::parse($info->end);

        $bookings = Booking::query()
            ->whereIn('user_id', $userIds)
            ->where('start_at', '<', $end)
            ->where(fn (Builder $query) => $query
                ->whereNull('end_at')
                ->orWhere('end_at', '>', $start))
            ->with('user')
            ->get();

        $events = collect();

        foreach ($bookings as $booking) {
            $bookingEnd = $booking->end_at ?? Carbon::now();

            $events->push(
                CalendarEvent::make($booking)
                    ->title("{$booking->user->name}: Arbeit {$booking->start_at->format('H:i')}–".($booking->end_at ? $booking->end_at->format('H:i') : 'läuft'))
                    ->start($booking->start_at)
                    ->end($bookingEnd)
                    ->backgroundColor('#22c55e')
                    ->textColor('#ffffff'),
            );
        }

        return $events;
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    private function todoEvents(FetchInfo $info): Collection
    {
        if (count($this->pageFilters['userIds']) == 0) {
            $userIds = User::all()->pluck('id')->toArray();
        } else {
            $userIds = $this->pageFilters['userIds'];
        }

        // TODO: Das hier prüfen
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
                        ->url(TodoResource::getUrl('edit', ['record' => $todo->id]))
                        ->textColor('#ffffff'),
                );
            }

            if ($todo->follow_up) {
                $events->push(
                    CalendarEvent::make()
                        ->title("WV: {$todo->name}")
                        ->start($todo->follow_up)
                        ->end($todo->follow_up)
                        ->allDay()
                        ->backgroundColor('#3b82f6')
                        ->url(TodoResource::getUrl('edit', ['record' => $todo->id]))
                        ->textColor('#ffffff'),
                );
            }
        }

        return $events;
    }
}
