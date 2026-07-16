<?php

namespace Tests\Feature\TimeClock;

use App\Enums\TimeEntryType;
use App\Filament\Resources\TimeEntries\Widgets\WorkTimeCalendarWidget;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkTimeCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/work-time-calendar-page')->assertOk();
    }

    public function test_calendar_widget_returns_time_entry_and_todo_events(): void
    {
        $user = User::factory()->create();

        TimeEntry::create([
            'user_id' => $user->id,
            'type' => TimeEntryType::Come,
            'happened_at' => '2026-07-20 08:00:00',
            'recorded_by_user_id' => $user->id,
        ]);
        TimeEntry::create([
            'user_id' => $user->id,
            'type' => TimeEntryType::Go,
            'happened_at' => '2026-07-20 16:00:00',
            'recorded_by_user_id' => $user->id,
        ]);

        Todo::create([
            'name' => 'Testaufgabe',
            'user_id' => $user->id,
            'todoable_type' => User::class,
            'todoable_id' => $user->id,
            'due_date' => '2026-07-20',
        ]);

        $this->actingAs($user);

        $events = Livewire::test(WorkTimeCalendarWidget::class)
            ->instance()
            ->getEventsJs([
                'startStr' => '2026-07-19',
                'endStr' => '2026-07-27',
                'tzOffset' => 0,
            ]);

        $this->assertNotEmpty($events);
        $titles = array_column($events, 'title');
        $this->assertTrue(collect($titles)->contains(fn ($title) => str_contains($title, 'Arbeit')));
        $this->assertTrue(collect($titles)->contains(fn ($title) => str_contains($title, 'Fällig')));
    }
}
