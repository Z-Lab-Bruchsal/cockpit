<?php

namespace App\Http\Controllers\Homepage;

use App\Enums\CourseAudience;
use App\Enums\CourseInstanceStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseDate;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CourseCalendarController extends Controller
{
    /**
     * Longest period a single event request may cover, to keep responses small.
     */
    protected const MAX_RANGE_DAYS = 100;

    public function index(): View
    {
        return view('homepage.courses.calendar');
    }

    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'zielgruppe' => ['nullable', 'string'],
        ]);

        $start = Carbon::parse($validated['start']);
        $end = Carbon::parse($validated['end']);

        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            $end = $start->copy()->addDays(self::MAX_RANGE_DAYS);
        }

        $audience = CourseAudience::tryFrom($validated['zielgruppe'] ?? '');

        $dates = CourseDate::query()
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->whereHas('courseInstance', fn (Builder $instances) => $instances
                ->whereIn('status', [CourseInstanceStatus::Published, CourseInstanceStatus::Cancelled])
                ->whereHas('course', fn (Builder $courses) => $courses
                    ->published()
                    ->when($audience, fn (Builder $query) => $query->where('audience', $audience))))
            ->with(['courseInstance.course', 'courseInstance.firstDate'])
            ->orderBy('starts_at')
            ->get();

        return response()->json($dates->map(function (CourseDate $date): array {
            $instance = $date->courseInstance;
            $availability = $instance->availability();

            return [
                'id' => $date->id,
                'title' => $instance->displayTitle(),
                'start' => $date->starts_at->toIso8601String(),
                'end' => $date->ends_at->toIso8601String(),
                'url' => $instance->course->url().'#durchfuehrung-'.$instance->id,
                'backgroundColor' => $availability->hexColor(),
                'borderColor' => $availability->hexColor(),
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'availability' => $availability->getLabel(),
                    'location' => $date->effectiveLocation(),
                ],
            ];
        })->values());
    }
}
