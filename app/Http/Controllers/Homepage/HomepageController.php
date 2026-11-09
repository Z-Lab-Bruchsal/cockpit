<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HomepageController extends Controller
{
    public function index(): View
    {
        return view('homepage.section', [
            'section' => Section::home(),
            'ancestors' => [],
        ]);
    }

    public function section(string $slug): View|RedirectResponse
    {
        $section = Section::query()
            ->where('slug', $slug)
            ->with('parent')
            ->firstOrFail();

        if ($section->is_home) {
            return redirect()->route('homepage.index', status: 301);
        }

        abort_unless($section->isPubliclyVisible(), 404);

        return view('homepage.section', [
            'section' => $section,
            'ancestors' => $section->ancestors(),
        ]);
    }
}
