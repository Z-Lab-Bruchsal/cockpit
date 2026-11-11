<?php

namespace App\Services\Reports;

use App\Enums\CourseAudience;
use App\Enums\CourseInstanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationStatus;
use App\Models\Booking;
use App\Models\CourseInstance;
use App\Models\Enrollment;
use App\Models\Interest;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Figures for the analytics widgets, the analytics page and its PDF.
 *
 * Definitions:
 * - A course run (CourseInstance) belongs to the period when its first date starts in it; drafts are ignored,
 *   cancelled runs are only counted as cancelled.
 * - A participation is a confirmed enrollment of a run that was not cancelled. The age is the kid's age at
 *   the first date (birthdate stored at booking, otherwise the kid's birthdate).
 * - Order costs are the total prices of orders placed (order date) in the period.
 * - Work hours are summed per calendar week (Monday to Sunday, business timezone) for staff with agreed weekly hours.
 */
class AnalyticsService
{
    public const string UNKNOWN_AGE = 'unbekannt';

    public const string WITHOUT_INTEREST = 'Ohne Interesse';

    public const string WITHOUT_MERCHANT = 'Ohne Händler';

    /**
     * @var array<string, Collection<int, CourseInstance>>
     */
    private array $instancesByFilters = [];

    /**
     * Course runs whose first date lies in the period and that match the course filters.
     *
     * @return Collection<int, CourseInstance>
     */
    public function instances(AnalyticsFilters $filters): Collection
    {
        return $this->instancesByFilters[serialize($filters)] ??= $this->loadInstances($filters);
    }

    /**
     * @return Collection<int, CourseInstance>
     */
    protected function loadInstances(AnalyticsFilters $filters): Collection
    {
        $start = $filters->start();
        $end = $filters->end();

        return CourseInstance::query()
            ->with(['course.interests', 'firstDate', 'enrollments.kid', 'registrations'])
            ->where('status', '!=', CourseInstanceStatus::Draft)
            ->whereHas('dates', fn (Builder $query) => $query->where('starts_at', '>=', $start)->where('starts_at', '<', $end))
            ->when($filters->courseIds !== [], fn (Builder $query) => $query->whereIn('course_id', $filters->courseIds))
            ->when($filters->audience !== null, fn (Builder $query) => $query->whereHas('course', fn (Builder $course) => $course->where('audience', $filters->audience)))
            ->when($filters->interestIds !== [], fn (Builder $query) => $query->whereHas('course.interests', fn (Builder $interest) => $interest->whereIn('interests.id', $filters->interestIds)))
            ->get()
            ->filter(fn (CourseInstance $instance): bool => $instance->firstDate !== null
                && $instance->firstDate->starts_at->greaterThanOrEqualTo($start)
                && $instance->firstDate->starts_at->lessThan($end))
            ->values();
    }

    /**
     * Confirmed enrollments of runs that took place, matching the age filter.
     *
     * @return Collection<int, array{enrollment: Enrollment, instance: CourseInstance, age: ?int, starts_at: CarbonInterface}>
     */
    public function participations(AnalyticsFilters $filters, EnrollmentStatus $status = EnrollmentStatus::Confirmed): Collection
    {
        return $this->instances($filters)
            ->reject(fn (CourseInstance $instance): bool => $instance->status === CourseInstanceStatus::Cancelled)
            ->flatMap(fn (CourseInstance $instance): Collection => $instance->enrollments
                ->filter(fn (Enrollment $enrollment): bool => $enrollment->status === $status)
                ->map(fn (Enrollment $enrollment): array => [
                    'enrollment' => $enrollment,
                    'instance' => $instance,
                    'age' => $this->ageAt($enrollment, $instance->firstDate->starts_at),
                    'starts_at' => $instance->firstDate->starts_at,
                ]))
            ->filter(fn (array $participation): bool => $filters->acceptsAge($participation['age']))
            ->values();
    }

    /**
     * @return array{courses: int, instances: int, cancelled: int, capacity: int, occupied: int, utilization: ?float}
     */
    public function courseSummary(AnalyticsFilters $filters): array
    {
        $instances = $this->instances($filters);
        [$cancelled, $held] = $instances->partition(fn (CourseInstance $instance): bool => $instance->status === CourseInstanceStatus::Cancelled);
        $kidRuns = $held->filter(fn (CourseInstance $instance): bool => $instance->course->audience === CourseAudience::Kids);

        $capacity = (int) $kidRuns->sum('capacity');
        $occupied = $kidRuns->sum(fn (CourseInstance $instance): int => $instance->enrollments->where('status', EnrollmentStatus::Confirmed)->count());

        return [
            'courses' => $held->pluck('course_id')->unique()->count(),
            'instances' => $held->count(),
            'cancelled' => $cancelled->count(),
            'capacity' => $capacity,
            'occupied' => $occupied,
            'utilization' => $capacity > 0 ? round($occupied / $capacity * 100, 1) : null,
        ];
    }

    /**
     * @return array{kids: int, enrollments: int, waitlisted: int, average_age: ?float, school_classes: int, students: int}
     */
    public function participantSummary(AnalyticsFilters $filters): array
    {
        $participations = $this->participations($filters);
        $ages = $participations->pluck('age')->filter(fn (?int $age): bool => $age !== null);

        $schoolRegistrations = $this->instances($filters)
            ->reject(fn (CourseInstance $instance): bool => $instance->status === CourseInstanceStatus::Cancelled)
            ->filter(fn (CourseInstance $instance): bool => $instance->course->audience === CourseAudience::SchoolClass)
            ->flatMap(fn (CourseInstance $instance): Collection => $instance->registrations->where('status', RegistrationStatus::Confirmed));

        return [
            'kids' => $participations->map(fn (array $participation): string => $this->kidKey($participation['enrollment']))->unique()->count(),
            'enrollments' => $participations->count(),
            'waitlisted' => $this->participations($filters, EnrollmentStatus::Waitlisted)->count(),
            'average_age' => $ages->isEmpty() ? null : round((float) $ages->avg(), 1),
            'school_classes' => $schoolRegistrations->count(),
            'students' => $schoolRegistrations->sum(fn (Registration $registration): int => $registration->schoolClass()['students']),
        ];
    }

    /**
     * Participations per age at course start; "unbekannt" when no birthdate is known.
     *
     * @return array<string, int>
     */
    public function ageDistribution(AnalyticsFilters $filters): array
    {
        $buckets = ['≤ 5' => 0];

        foreach (range(6, 15) as $age) {
            $buckets[(string) $age] = 0;
        }

        $buckets['16+'] = 0;
        $buckets[self::UNKNOWN_AGE] = 0;

        foreach ($this->participations($filters) as $participation) {
            $buckets[$this->ageBucket($participation['age'])]++;
        }

        return $buckets;
    }

    /**
     * @return array<string, int> month label => participations
     */
    public function enrollmentsPerMonth(AnalyticsFilters $filters): array
    {
        $months = $this->emptyMonths($filters);

        foreach ($this->participations($filters) as $participation) {
            $months[$this->monthLabel($participation['starts_at'])]++;
        }

        return $months;
    }

    /**
     * Participations per interest of the course, most first. A course with several interests counts for each.
     *
     * @return array<string, int>
     */
    public function enrollmentsPerInterest(AnalyticsFilters $filters): array
    {
        $counts = [];

        foreach ($this->participations($filters) as $participation) {
            $interests = $participation['instance']->course->interests
                ->when($filters->interestIds !== [], fn (Collection $interests): Collection => $interests->whereIn('id', $filters->interestIds));

            if ($interests->isEmpty()) {
                $counts[self::WITHOUT_INTEREST] = ($counts[self::WITHOUT_INTEREST] ?? 0) + 1;

                continue;
            }

            foreach ($interests as $interest) {
                /** @var Interest $interest */
                $counts[$interest->name] = ($counts[$interest->name] ?? 0) + 1;
            }
        }

        arsort($counts);

        return $counts;
    }

    /**
     * Kids with the most courses in the period.
     *
     * @return list<array{key: string, kid_id: ?int, name: string, courses: int, age: ?int, last_course: string}>
     */
    public function topKids(AnalyticsFilters $filters, int $limit = 10): array
    {
        return $this->participations($filters)
            ->groupBy(fn (array $participation): string => $this->kidKey($participation['enrollment']))
            ->map(function (Collection $group, string $key): array {
                $latest = $group->sortByDesc('starts_at')->first();
                $enrollment = $latest['enrollment'];

                return [
                    'key' => $key,
                    'kid_id' => $enrollment->kid_id,
                    'name' => $enrollment->kid?->name ?? $enrollment->kid_name,
                    'courses' => $group->pluck('instance.id')->unique()->count(),
                    'age' => $latest['age'],
                    'last_course' => $latest['instance']->course->name,
                ];
            })
            ->sortBy([['courses', 'desc'], ['name', 'asc']])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Courses with the most participants in the period.
     *
     * @return list<array{course_id: int, name: string, instances: int, participants: int, waitlisted: int, capacity: int, utilization: ?float}>
     */
    public function topCourses(AnalyticsFilters $filters, int $limit = 10): array
    {
        $participants = $this->participations($filters)->countBy(fn (array $participation): int => $participation['instance']->course_id);
        $waitlisted = $this->participations($filters, EnrollmentStatus::Waitlisted)->countBy(fn (array $participation): int => $participation['instance']->course_id);

        return $this->instances($filters)
            ->reject(fn (CourseInstance $instance): bool => $instance->status === CourseInstanceStatus::Cancelled)
            ->groupBy('course_id')
            ->map(function (Collection $instances, int $courseId) use ($participants, $waitlisted): array {
                $course = $instances->first()->course;
                $capacity = $course->audience === CourseAudience::Kids ? (int) $instances->sum('capacity') : 0;
                $count = (int) ($participants[$courseId] ?? 0);

                return [
                    'course_id' => $courseId,
                    'name' => $course->name,
                    'instances' => $instances->count(),
                    'participants' => $count,
                    'waitlisted' => (int) ($waitlisted[$courseId] ?? 0),
                    'capacity' => $capacity,
                    'utilization' => $capacity > 0 ? round($count / $capacity * 100, 1) : null,
                ];
            })
            ->sortBy([['participants', 'desc'], ['name', 'asc']])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Orders placed in the period that match the order filters.
     *
     * @return Collection<int, Order>
     */
    public function orders(AnalyticsFilters $filters): Collection
    {
        return Order::query()
            ->with(['merchant', 'orderstatus'])
            ->where('orderdatetime', '>=', $filters->start())
            ->where('orderdatetime', '<', $filters->end())
            ->when($filters->merchantIds !== [], fn (Builder $query) => $query->whereIn('merchant_id', $filters->merchantIds))
            ->when($filters->orderstatusIds !== [], fn (Builder $query) => $query->whereIn('orderstatus_id', $filters->orderstatusIds))
            ->get();
    }

    /**
     * @return array{total: float, count: int, average: ?float, without_price: int}
     */
    public function orderSummary(AnalyticsFilters $filters): array
    {
        $orders = $this->orders($filters);
        $priced = $orders->whereNotNull('total_price');

        return [
            'total' => round((float) $priced->sum('total_price'), 2),
            'count' => $orders->count(),
            'average' => $priced->isEmpty() ? null : round((float) $priced->avg('total_price'), 2),
            'without_price' => $orders->count() - $priced->count(),
        ];
    }

    /**
     * @return array<string, float> month label => costs
     */
    public function orderCostsPerMonth(AnalyticsFilters $filters): array
    {
        $months = array_map(fn (): float => 0.0, $this->emptyMonths($filters));

        foreach ($this->orders($filters) as $order) {
            $months[$this->monthLabel($order->orderdatetime)] += (float) $order->total_price;
        }

        return array_map(fn (float $sum): float => round($sum, 2), $months);
    }

    /**
     * @return array<string, float> merchant => costs, highest first
     */
    public function orderCostsPerMerchant(AnalyticsFilters $filters): array
    {
        return $this->orders($filters)
            ->groupBy(fn (Order $order): string => $order->merchant?->name ?? self::WITHOUT_MERCHANT)
            ->map(fn (Collection $orders): float => round((float) $orders->sum('total_price'), 2))
            ->sortDesc()
            ->all();
    }

    /**
     * Calendar weeks of the period up to the current week; the period is widened to whole weeks.
     *
     * @return array<string, CarbonImmutable> label => Monday 00:00 in the business timezone
     */
    public function weeks(AnalyticsFilters $filters): array
    {
        $week = $filters->from->startOfWeek(CarbonInterface::MONDAY);
        $last = $filters->until->min(CarbonImmutable::now(config('app.business_timezone')))->startOfWeek(CarbonInterface::MONDAY);
        $weeks = [];

        while ($week->lessThanOrEqualTo($last)) {
            $weeks[$this->weekLabel($week)] = $week;
            $week = $week->addWeek();
        }

        return $weeks;
    }

    /**
     * Worked minutes per week compared to the agreed weekly hours, for every user with agreed hours.
     *
     * @return list<array{user_id: int, name: string, weekly_hours: float, target_minutes: int, current_week_minutes: int, average_week_minutes: int, max_week_minutes: int, weeks_over: int, overtime_minutes: int, weeks: array<string, int>}>
     */
    public function workHours(AnalyticsFilters $filters): array
    {
        $timezone = config('app.business_timezone');
        $weeks = $this->weeks($filters);
        $users = User::query()
            ->whereNotNull('weekly_hours')
            ->when($filters->userIds !== [], fn (Builder $query) => $query->whereKey($filters->userIds))
            ->orderBy('name')
            ->get();

        if ($users->isEmpty()) {
            return [];
        }

        $currentWeek = CarbonImmutable::now($timezone)->startOfWeek(CarbonInterface::MONDAY);
        $start = collect($weeks)->first()?->min($currentWeek) ?? $currentWeek;
        $end = (collect($weeks)->last()?->max($currentWeek) ?? $currentWeek)->addWeek();

        $bookings = Booking::query()
            ->whereIn('user_id', $users->modelKeys())
            ->where('start_at', '>=', $start->utc())
            ->where('start_at', '<', $end->utc())
            ->get(['user_id', 'start_at', 'worked_minutes'])
            ->groupBy('user_id');

        return $users->map(function (User $user) use ($bookings, $weeks, $currentWeek, $timezone): array {
            $perWeek = ($bookings[$user->id] ?? collect())
                ->groupBy(fn (Booking $booking): string => $this->weekLabel($booking->start_at->copy()->setTimezone($timezone)->toImmutable()->startOfWeek(CarbonInterface::MONDAY)))
                ->map(fn (Collection $group): int => (int) $group->sum('worked_minutes'));

            $minutes = array_map(fn (string $label): int => (int) ($perWeek[$label] ?? 0), array_combine(array_keys($weeks), array_keys($weeks)));
            $target = (int) round((float) $user->weekly_hours * 60);
            $over = array_filter($minutes, fn (int $worked): bool => $worked > $target);

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'weekly_hours' => (float) $user->weekly_hours,
                'target_minutes' => $target,
                'current_week_minutes' => (int) ($perWeek[$this->weekLabel($currentWeek)] ?? 0),
                'average_week_minutes' => $minutes === [] ? 0 : (int) round(array_sum($minutes) / count($minutes)),
                'max_week_minutes' => $minutes === [] ? 0 : max($minutes),
                'weeks_over' => count($over),
                'overtime_minutes' => array_sum(array_map(fn (int $worked): int => $worked - $target, $over)),
                'weeks' => $minutes,
            ];
        })->all();
    }

    public function ageBucket(?int $age): string
    {
        return match (true) {
            $age === null => self::UNKNOWN_AGE,
            $age <= 5 => '≤ 5',
            $age >= 16 => '16+',
            default => (string) $age,
        };
    }

    protected function ageAt(Enrollment $enrollment, CarbonInterface $date): ?int
    {
        $birthdate = $enrollment->kid_birthdate ?? $enrollment->kid?->birthdate;

        if ($birthdate === null || $birthdate->greaterThan($date)) {
            return null;
        }

        return (int) $birthdate->diffInYears($date);
    }

    protected function kidKey(Enrollment $enrollment): string
    {
        return $enrollment->kid_id !== null ? 'kid-'.$enrollment->kid_id : 'enrollment-'.$enrollment->id;
    }

    /**
     * @return array<string, int>
     */
    protected function emptyMonths(AnalyticsFilters $filters): array
    {
        $months = [];
        $month = $filters->from->startOfMonth();

        while ($month->lessThanOrEqualTo($filters->until)) {
            $months[$this->monthLabel($month)] = 0;
            $month = $month->addMonth();
        }

        return $months;
    }

    protected function monthLabel(CarbonInterface $date): string
    {
        return $date->copy()->setTimezone(config('app.business_timezone'))->locale('de')->translatedFormat('M Y');
    }

    protected function weekLabel(CarbonInterface $monday): string
    {
        return 'KW '.$monday->format('W/o');
    }
}
