<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Contracts\View\View;

class HomepageController extends Controller
{
    public function index(): View
    {
        $tiles = Section::query()
            ->visible()
            ->roots()
            ->where('is_standalone', false)
            ->get();

        return view('homepage.index', ['tiles' => $tiles]);
    }

    public function section(string $slug): View
    {
        $section = Section::query()
            ->where('slug', $slug)
            ->with(['visibleChildren', 'parent'])
            ->firstOrFail();

        abort_unless($section->isPubliclyVisible(), 404);

        return view('homepage.section', [
            'section' => $section,
            'ancestors' => $section->ancestors(),
            'tiles' => $section->tileChildren(),
        ]);
    }
}
