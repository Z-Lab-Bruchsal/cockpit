<?php

namespace App\View\Components\Homepage;

use App\Models\CompanySetting;
use App\Models\Section;
use App\Services\Registrations\CartManager;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class Layout extends Component
{
    public CompanySetting $settings;

    /**
     * @var Collection<int, Section>
     */
    public Collection $navigation;

    public bool $isLoggedIn;

    public string $accountUrl;

    public int $cartCount;

    public function __construct(public ?string $title = null)
    {
        $this->settings = CompanySetting::current();

        $this->navigation = Section::query()
            ->visible()
            ->roots()
            ->where('is_standalone', false)
            ->where('show_in_navigation', true)
            ->get();

        $this->isLoggedIn = Auth::guard('contact')->check();
        $this->accountUrl = route($this->isLoggedIn ? 'homepage.account' : 'homepage.login');
        $this->cartCount = app(CartManager::class)->current()?->heldRegistrations()->count() ?? 0;
    }

    public function pageTitle(): string
    {
        return collect([$this->title, $this->settings->homepageTitle()])->filter()->implode(' – ');
    }

    public function render(): View|Closure|string
    {
        return view('components.homepage.layout');
    }
}
