<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseAvailability;
use App\Models\CourseInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_instance_with_free_seats_is_available(): void
    {
        $instance = CourseInstance::factory()->withDates()->create(['capacity' => 10]);

        $this->assertSame(CourseAvailability::Available, $instance->availability());
        $this->assertSame(10, $instance->freeSeats());
        $this->assertTrue($instance->availability()->acceptsRegistrations());
    }

    public function test_draft_and_cancelled_instances_do_not_accept_registrations(): void
    {
        $this->assertSame(CourseAvailability::Closed, CourseInstance::factory()->draft()->withDates()->create()->availability());
        $this->assertSame(CourseAvailability::Cancelled, CourseInstance::factory()->cancelled()->withDates()->create()->availability());
    }

    public function test_registration_window_is_respected(): void
    {
        $notYetOpen = CourseInstance::factory()->withDates()->create(['registration_opens_at' => now()->addDay()]);
        $closedExplicitly = CourseInstance::factory()->withDates()->create(['registration_closes_at' => now()->subHour()]);
        $alreadyStarted = CourseInstance::factory()->withDates(3, now()->subDay())->create();

        $this->assertSame(CourseAvailability::NotYetOpen, $notYetOpen->availability());
        $this->assertSame(CourseAvailability::Closed, $closedExplicitly->availability());
        $this->assertSame(CourseAvailability::Closed, $alreadyStarted->availability());
    }

    public function test_upcoming_scope_ignores_instances_whose_dates_are_over(): void
    {
        $past = CourseInstance::factory()->withDates(1, now()->subWeek())->create();
        $running = CourseInstance::factory()->withDates(3, now()->subWeek())->create();
        $future = CourseInstance::factory()->withDates()->create();

        $upcomingIds = CourseInstance::query()->upcoming()->pluck('id');

        $this->assertNotContains($past->id, $upcomingIds);
        $this->assertContains($running->id, $upcomingIds);
        $this->assertContains($future->id, $upcomingIds);
    }
}
