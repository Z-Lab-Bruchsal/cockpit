<?php

namespace Tests\Feature\TimeClock;

use App\Filament\Resources\Bookings\Widgets\WorkTimeCalendarWidget;
use App\Models\Booking;
use App\Models\Todo;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class WorkTimeCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShieldSeeder::class);
    }

    public function test_calendar_page_renders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Crew');

        $this->actingAs($user)->get('/cockpit/calendar-page')->assertOk();
    }

    public function test_calendar_widget_returns_booking_and_todo_events(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Crew');

        Booking::create([
            'user_id' => $user->id,
            'start_at' => '2026-07-20 08:00:00',
            'end_at' => '2026-07-20 16:00:00',
            'recorded_by_user_id' => $user->id,
        ]);

        Todo::create([
            'name' => 'Testaufgabe',
            'user_id' => $user->id,
            'due_date' => '2026-07-20',
        ])->assignedUsers()->attach($user);

        $this->actingAs($user);

        $timeTitles = $this->eventTitlesFor('times');
        $todoTitles = $this->eventTitlesFor('todos');

        $this->assertTrue($timeTitles->contains(fn ($title) => str_contains($title, 'Arbeit')));
        $this->assertTrue($todoTitles->contains(fn ($title) => str_contains($title, 'Fällig')));
    }

    /**
     * @return Collection<int, string>
     */
    private function eventTitlesFor(string $eventType): Collection
    {
        $events = Livewire::test(WorkTimeCalendarWidget::class, ['pageFilters' => ['eventType' => $eventType, 'userIds' => []]])
            ->instance()
            ->getEventsJs([
                'startStr' => '2026-07-19',
                'endStr' => '2026-07-27',
                'tzOffset' => 0,
            ]);

        return collect(array_column($events, 'title'));
    }
}
