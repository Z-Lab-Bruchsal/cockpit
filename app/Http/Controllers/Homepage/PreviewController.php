<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Section;
use App\Models\User;
use App\Services\Homepage\HomepagePreview;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Renders the cockpit previews of courses and sections, see HomepagePreview.
 */
class PreviewController extends Controller
{
    public function show(string $token, string $variant): Response
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        abort_if($user === null, 403);

        $snapshot = HomepagePreview::find($token, $user->id);

        abort_if($snapshot === null, 404);

        $view = match ($snapshot['type']) {
            HomepagePreview::TYPE_COURSE => $this->course($user, $snapshot, $variant),
            HomepagePreview::TYPE_SECTION => $this->section($user, $snapshot, $variant),
            default => abort(404),
        };

        return response($view)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * @param  array{type: string, user_id: int, record_id: int|null, state: array<string, mixed>}  $snapshot
     */
    protected function course(User $user, array $snapshot, string $variant): string
    {
        $course = HomepagePreview::buildCourse($snapshot);

        abort_unless($course->exists ? $user->can('update', $course) : $user->can('create', Course::class), 403);

        if ($variant === 'karte') {
            return view('homepage.preview.course-card', ['course' => $course])->render();
        }

        return view('homepage.courses.show', [
            'course' => $course,
            'template' => $course->effectiveTemplate(),
            'preview' => true,
        ])->render();
    }

    /**
     * @param  array{type: string, user_id: int, record_id: int|null, state: array<string, mixed>}  $snapshot
     */
    protected function section(User $user, array $snapshot, string $variant): string
    {
        $section = HomepagePreview::buildSection($snapshot);

        abort_unless($section->exists ? $user->can('update', $section) : $user->can('create', Section::class), 403);

        if ($variant === 'karte') {
            return view('homepage.preview.section-tile', ['section' => $section])->render();
        }

        return view('homepage.section', [
            'section' => $section,
            'ancestors' => $section->ancestors(),
            'tiles' => $section->exists ? $section->tileChildren() : collect(),
            'preview' => true,
        ])->render();
    }
}
