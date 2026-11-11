<?php

namespace Tests\Feature\Analytics;

use App\Enums\CourseAudience;
use App\Enums\EnrollmentStatus;
use App\Models\Booking;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Enrollment;
use App\Models\Interest;
use App\Models\Kid;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use App\Services\Reports\AnalyticsFilters;
use App\Services\Reports\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 12:00', 'Europe/Berlin'));
    }

    protected function service(): AnalyticsService
    {
        return app(AnalyticsService::class);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function filters(array $state = []): AnalyticsFilters
    {
        return AnalyticsFilters::fromArray(['from' => '2026-01-01', 'to' => '2026-12-31', ...$state]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function courseRun(Course $course, string $firstStart, array $attributes = [], int $dates = 1): CourseInstance
    {
        return CourseInstance::factory()
            ->for($course)
            ->withDates($dates, Carbon::parse($firstStart, 'Europe/Berlin')->utc())
            ->create($attributes);
    }

    protected function enroll(CourseInstance $instance, ?Kid $kid = null, EnrollmentStatus $status = EnrollmentStatus::Confirmed, ?string $birthdate = null): Enrollment
    {
        return Enrollment::factory()->create([
            'registration_id' => Registration::factory()->create(['course_instance_id' => $instance->id])->id,
            'kid_id' => $kid?->id,
            'kid_name' => $kid?->name ?? 'Ohne Konto',
            'kid_birthdate' => $birthdate,
            'status' => $status,
        ]);
    }

    public function test_course_summary_counts_runs_whose_first_date_lies_in_the_period(): void
    {
        $course = Course::factory()->create();
        $other = Course::factory()->create();

        $held = $this->courseRun($course, '2026-03-10 16:00', ['capacity' => 10]);
        $this->enroll($held);
        $this->enroll($held);
        $this->enroll($held, status: EnrollmentStatus::Waitlisted);
        $this->enroll($held, status: EnrollmentStatus::Cancelled);

        $this->courseRun($other, '2026-05-10 16:00')->update(['status' => 'cancelled']);
        $this->courseRun($other, '2025-12-25 16:00', dates: 2);
        $this->courseRun($other, '2026-06-10 16:00', ['status' => 'draft']);

        $this->assertSame([
            'courses' => 1,
            'instances' => 1,
            'cancelled' => 1,
            'capacity' => 10,
            'occupied' => 2,
            'utilization' => 20.0,
        ], $this->service()->courseSummary($this->filters()));
    }

    public function test_participant_summary_counts_distinct_kids_waitlist_age_and_school_classes(): void
    {
        $course = Course::factory()->create();
        $first = $this->courseRun($course, '2026-03-01 16:00');
        $second = $this->courseRun($course, '2026-09-01 16:00');
        $anna = Kid::factory()->create(['birthdate' => '2016-01-15']);
        $ben = Kid::factory()->create(['birthdate' => '2014-01-15']);

        $this->enroll($first, $anna, birthdate: '2016-01-15');
        $this->enroll($second, $anna, birthdate: '2016-01-15');
        $this->enroll($first, $ben, birthdate: '2014-01-15');
        $this->enroll($first, null);
        $this->enroll($second, $ben, EnrollmentStatus::Waitlisted);

        $schoolRun = $this->courseRun(Course::factory()->forSchoolClasses()->create(), '2026-04-01 09:00');
        Registration::factory()->create(['course_instance_id' => $schoolRun->id, 'school_class_data' => ['class_name' => '4a', 'students' => 23]]);
        Registration::factory()->create(['course_instance_id' => $schoolRun->id, 'school_class_data' => ['class_name' => '4b', 'students' => 19], 'status' => 'cancelled']);

        $summary = $this->service()->participantSummary($this->filters());

        $this->assertSame(3, $summary['kids']);
        $this->assertSame(4, $summary['enrollments']);
        $this->assertSame(1, $summary['waitlisted']);
        $this->assertSame(10.7, $summary['average_age']);
        $this->assertSame(1, $summary['school_classes']);
        $this->assertSame(23, $summary['students']);
    }

    public function test_age_distribution_uses_the_age_at_course_start(): void
    {
        $instance = $this->courseRun(Course::factory()->create(), '2026-03-01 16:00');

        $this->enroll($instance, birthdate: '2016-03-02');
        $this->enroll($instance, Kid::factory()->create(['birthdate' => '2016-03-01']));
        $this->enroll($instance, Kid::factory()->create(['birthdate' => null]));
        $this->enroll($instance, birthdate: '2022-01-01');
        $this->enroll($instance, birthdate: '2009-01-01');

        $distribution = $this->service()->ageDistribution($this->filters());

        $this->assertSame(1, $distribution['9']);
        $this->assertSame(1, $distribution['10']);
        $this->assertSame(1, $distribution['≤ 5']);
        $this->assertSame(1, $distribution['16+']);
        $this->assertSame(1, $distribution[AnalyticsService::UNKNOWN_AGE]);
        $this->assertSame(5, array_sum($distribution));
    }

    public function test_filters_by_interest_course_audience_and_age(): void
    {
        $robotics = Interest::factory()->create(['name' => 'Robotik']);
        $art = Interest::factory()->create(['name' => 'Kunst']);
        $robotCourse = Course::factory()->hasAttached($robotics, [], 'interests')->create();
        $artCourse = Course::factory()->hasAttached($art, [], 'interests')->create();
        $schoolCourse = Course::factory()->forSchoolClasses()->hasAttached($robotics, [], 'interests')->create();

        $this->enroll($this->courseRun($robotCourse, '2026-03-01 16:00'), birthdate: '2018-01-01');
        $this->enroll($this->courseRun($robotCourse, '2026-04-01 16:00'), birthdate: '2014-01-01');
        $this->enroll($this->courseRun($artCourse, '2026-03-01 16:00'), birthdate: '2016-01-01');
        $this->enroll($this->courseRun($schoolCourse, '2026-03-01 09:00'), birthdate: '2016-01-01');

        $this->assertSame(4, $this->service()->participantSummary($this->filters())['enrollments']);
        $this->assertSame(3, $this->service()->participantSummary($this->filters(['interests' => [$robotics->id]]))['enrollments']);
        $this->assertSame(2, $this->service()->participantSummary($this->filters(['interests' => [$robotics->id], 'audience' => CourseAudience::Kids->value]))['enrollments']);
        $this->assertSame(1, $this->service()->participantSummary($this->filters(['courses' => [$artCourse->id]]))['enrollments']);
        $this->assertSame(2, $this->service()->participantSummary($this->filters(['age_min' => '9', 'age_max' => '11']))['enrollments']);
        $this->assertSame(['Robotik' => 2], $this->service()->enrollmentsPerInterest($this->filters(['interests' => [$robotics->id], 'age_min' => '10'])));
    }

    public function test_enrollments_per_month_and_per_interest(): void
    {
        $robotics = Interest::factory()->create(['name' => 'Robotik']);
        $art = Interest::factory()->create(['name' => 'Kunst']);
        $course = Course::factory()->hasAttached([$robotics, $art], [], 'interests')->create();
        $plain = Course::factory()->create();

        $march = $this->courseRun($course, '2026-03-31 23:30');
        $this->enroll($march);
        $this->enroll($march);
        $this->enroll($this->courseRun($plain, '2026-04-02 16:00'));

        $months = $this->service()->enrollmentsPerMonth($this->filters());

        $this->assertCount(12, $months);
        $this->assertSame(2, $months['Mär. 2026'] ?? $months['Mär 2026']);
        $this->assertSame(1, $months['Apr. 2026'] ?? $months['Apr 2026']);
        $this->assertSame(['Robotik' => 2, 'Kunst' => 2, AnalyticsService::WITHOUT_INTEREST => 1], $this->service()->enrollmentsPerInterest($this->filters()));
    }

    public function test_top_kids_and_top_courses_are_sorted_by_participation(): void
    {
        $popular = Course::factory()->create(['name' => 'Löten']);
        $quiet = Course::factory()->create(['name' => 'Nähen']);
        $anna = Kid::factory()->create(['name' => 'Anna']);
        $ben = Kid::factory()->create(['name' => 'Ben']);

        $first = $this->courseRun($popular, '2026-02-01 16:00', ['capacity' => 4]);
        $second = $this->courseRun($popular, '2026-03-01 16:00', ['capacity' => 4]);
        $third = $this->courseRun($quiet, '2026-04-01 16:00', ['capacity' => 4]);

        $this->enroll($first, $ben);
        $this->enroll($second, $ben);
        $this->enroll($third, $ben);
        $this->enroll($first, $anna);
        $this->enroll($third, $anna, EnrollmentStatus::Waitlisted);

        $kids = $this->service()->topKids($this->filters());

        $this->assertSame(['Ben', 'Anna'], array_column($kids, 'name'));
        $this->assertSame([3, 1], array_column($kids, 'courses'));
        $this->assertSame('Nähen', $kids[0]['last_course']);

        $courses = $this->service()->topCourses($this->filters());

        $this->assertSame(['Löten', 'Nähen'], array_column($courses, 'name'));
        $this->assertSame([2, 1], array_column($courses, 'instances'));
        $this->assertSame([3, 1], array_column($courses, 'participants'));
        $this->assertSame([0, 1], array_column($courses, 'waitlisted'));
        $this->assertSame([37.5, 25.0], array_column($courses, 'utilization'));
    }

    public function test_order_costs_per_period_merchant_and_status(): void
    {
        $reichelt = Merchant::factory()->create(['name' => 'Reichelt']);
        $conrad = Merchant::factory()->create(['name' => 'Conrad']);

        Order::factory()->ordered(['merchant_id' => $reichelt->id, 'orderdatetime' => '2026-02-10 10:00', 'total_price' => 100])->create();
        Order::factory()->ordered(['merchant_id' => $reichelt->id, 'orderdatetime' => '2026-03-10 10:00', 'total_price' => 50.5])->create();
        Order::factory()->ordered(['merchant_id' => $conrad->id, 'orderdatetime' => '2026-03-11 10:00', 'total_price' => null])->create();
        Order::factory()->ordered(['merchant_id' => null, 'orderdatetime' => '2026-03-12 10:00', 'total_price' => 20])->create();
        Order::factory()->ordered(['merchant_id' => $conrad->id, 'orderdatetime' => '2025-12-31 10:00', 'total_price' => 999])->create();
        Order::factory()->create(['total_price' => 500]);

        $this->assertSame(['total' => 170.5, 'count' => 4, 'average' => 56.83, 'without_price' => 1], $this->service()->orderSummary($this->filters()));
        $this->assertSame(['Reichelt' => 150.5, AnalyticsService::WITHOUT_MERCHANT => 20.0, 'Conrad' => 0.0], $this->service()->orderCostsPerMerchant($this->filters()));
        $this->assertSame(170.5, array_sum($this->service()->orderCostsPerMonth($this->filters())));
        $this->assertSame(150.5, $this->service()->orderSummary($this->filters(['merchants' => [$reichelt->id]]))['total']);

        $statusId = Order::query()->value('orderstatus_id');
        $this->assertSame(4, $this->service()->orderSummary($this->filters(['orderstatus' => [$statusId]]))['count']);
        $this->assertSame(0, $this->service()->orderSummary($this->filters(['orderstatus' => [$statusId + 100]]))['count']);
    }

    public function test_work_hours_flag_weeks_above_the_agreed_weekly_hours(): void
    {
        $leader = User::factory()->create(['name' => 'Lea', 'weekly_hours' => 10]);
        User::factory()->create(['name' => 'Ohne Soll']);

        $book = fn (string $start, int $minutes) => Booking::factory()->create([
            'user_id' => $leader->id,
            'start_at' => Carbon::parse($start, 'Europe/Berlin')->utc(),
            'end_at' => Carbon::parse($start, 'Europe/Berlin')->utc()->addMinutes($minutes),
            'worked_minutes' => $minutes,
        ]);

        $book('2026-10-27 09:00', 480);
        $book('2026-11-03 09:00', 600);
        $book('2026-11-08 23:30', 120);
        $book('2026-11-10 09:00', 180);
        $book('2026-11-12 09:00', 60);

        $rows = $this->service()->workHours($this->filters(['from' => '2026-10-28', 'to' => '2026-12-31']));

        $this->assertCount(1, $rows);
        $this->assertSame('Lea', $rows[0]['name']);
        $this->assertSame(600, $rows[0]['target_minutes']);
        $this->assertSame(['KW 44/2026' => 480, 'KW 45/2026' => 720, 'KW 46/2026' => 240], $rows[0]['weeks']);
        $this->assertSame(240, $rows[0]['current_week_minutes']);
        $this->assertSame(1, $rows[0]['weeks_over']);
        $this->assertSame(120, $rows[0]['overtime_minutes']);
        $this->assertSame(720, $rows[0]['max_week_minutes']);
        $this->assertSame(480, $rows[0]['average_week_minutes']);

        $this->assertSame([], $this->service()->workHours($this->filters(['users' => [$leader->id + 100]])));
    }

    public function test_invalid_filter_input_falls_back_to_the_current_year(): void
    {
        $filters = AnalyticsFilters::fromArray([
            'from' => 'kaputt',
            'to' => null,
            'interests' => ['3', 'x', -1],
            'audience' => 'unknown',
            'age_min' => 'abc',
            'age_max' => '500',
        ]);

        $this->assertSame('2026-01-01', $filters->from->toDateString());
        $this->assertSame('2026-12-31', $filters->until->toDateString());
        $this->assertSame([3], $filters->interestIds);
        $this->assertNull($filters->audience);
        $this->assertFalse($filters->hasAgeFilter());

        $reversed = AnalyticsFilters::fromArray(['from' => '2026-05-01', 'to' => '2026-04-01']);
        $this->assertSame('2026-05-01', $reversed->until->toDateString());
        $this->assertSame('2026-04-30 22:00:00', $reversed->start()->toDateTimeString());
    }
}
