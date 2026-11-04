<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use Illuminate\Contracts\View\View;

class NewsController extends Controller
{
    public function index(): View
    {
        $posts = NewsPost::query()
            ->published()
            ->paginate(6);

        return view('homepage.news.index', ['posts' => $posts]);
    }

    public function show(string $slug): View
    {
        $post = NewsPost::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('homepage.news.show', ['post' => $post]);
    }
}
