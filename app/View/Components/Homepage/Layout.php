<?php

namespace App\View\Components\Homepage;

use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\DesignTheme;
use App\Models\Section;
use App\Services\Homepage\InlineEditing;
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

    public bool $isSchoolContact;

    public bool $isAccountPage;

    /**
     * Cockpit users who may change the company settings also arrange the footer in the edit mode.
     */
    public bool $canArrangeFooter;

    /**
     * Cockpit users who may edit or arrange anything in place, see InlineEditing.
     */
    public bool $canEditInline;

    public bool $isEditingInline;

    /**
     * Cockpit users with the permission "Homepage:Design" may change the homepage colors in place.
     */
    public bool $canDesign;

    public bool $isDesigning;

    public function __construct(public ?string $title = null, public bool $isPreview = false)
    {
        $this->settings = CompanySetting::current();

        $this->navigation = Section::query()
            ->visible()
            ->roots()
            ->where('is_standalone', false)
            ->where('is_home', false)
            ->where('show_in_navigation', true)
            ->get();

        /** @var Contact|null $contact */
        $contact = Auth::guard('contact')->user();
        $this->isLoggedIn = $contact !== null;
        $this->isSchoolContact = (bool) $contact?->canBookForSchool();
        $this->accountUrl = route(match (true) {
            $this->isSchoolContact => 'homepage.schools.account',
            $this->isLoggedIn => 'homepage.account',
            default => 'homepage.login',
        });
        $this->isAccountPage = request()->routeIs('homepage.account', 'homepage.login', 'homepage.schools.account');
        $this->canArrangeFooter = (bool) Auth::guard('web')->user()?->can('View:CompanySettingsPage');
        $this->canEditInline = InlineEditing::isAvailable();
        $this->isEditingInline = InlineEditing::isActive();
        $this->canDesign = DesignTheme::userMayDesign();
        $this->isDesigning = ! $this->isPreview && DesignTheme::isDesignModeActive();
    }

    /**
     * The floating toolbar for cockpit users: edit mode (with arranging tiles and footer) and design mode.
     */
    public function showsToolbar(): bool
    {
        return ($this->canEditInline || $this->canDesign) && ! $this->isPreview;
    }

    public function pageTitle(): string
    {
        return collect([$this->title, $this->settings->homepageTitle()])->filter()->implode(' – ');
    }

    /**
     * Main navigation: visible root sections followed by the fixed app pages.
     *
     * @return list<array{label: string, url: string, active: bool}>
     */
    public function mainLinks(): array
    {
        return $this->navigation
            ->map(fn (Section $item): array => [
                'label' => $item->title,
                'url' => $item->url(),
                'active' => request()->is($item->slug),
            ])
            ->push(['label' => 'Kurse', 'url' => route('homepage.courses.index'), 'active' => request()->routeIs('homepage.courses.*')])
            ->push(['label' => 'Kalender', 'url' => route('homepage.calendar'), 'active' => request()->routeIs('homepage.calendar')])
            ->push(['label' => 'News', 'url' => route('homepage.news.index'), 'active' => request()->routeIs('homepage.news.*')])
            ->push(['label' => 'Schulen', 'url' => route('homepage.schools.index'), 'active' => request()->routeIs('homepage.schools.*')])
            ->values()
            ->all();
    }

    public function render(): View|Closure|string
    {
        return view('components.homepage.layout');
    }
}
