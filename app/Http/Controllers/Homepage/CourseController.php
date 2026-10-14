<?php

namespace App\Http\Controllers\Homepage;

use App\Enums\CourseAudience;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Interest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'interesse' => ['nullable', 'string', 'max:100'],
            'alter' => ['nullable', 'integer', 'min:0', 'max:99'],
            'zielgruppe' => ['nullable', 'string'],
        ]);

        $courses = Course::query()
            ->forHomepage(
                interestSlug: $filters['interesse'] ?? null,
                age: isset($filters['alter']) ? (int) $filters['alter'] : null,
                audience: CourseAudience::tryFrom($filters['zielgruppe'] ?? ''),
            )
            ->get();

        return view('homepage.courses.index', [
            'courses' => $courses,
            'interests' => Interest::query()->active()->whereHas('courses', fn ($query) => $query->published())->get(),
            'filters' => $filters,
        ]);
    }

    public function show(string $slug): View
    {
        $course = Course::query()
            ->published()
            ->where('slug', $slug)
            ->with(['interests', 'template', 'upcomingInstances.dates'])
            ->firstOrFail();

        return view('homepage.courses.show', [
            'course' => $course,
            'template' => $course->effectiveTemplate(),
        ]);
    }
}
