<?php

namespace App\Livewire\Homepage;

use App\Models\DesignTheme;
use App\Models\User;
use App\Services\Homepage\DesignRoles;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Design mode on the homepage (?design=1): pick an element, change the color of its role (or of just this element),
 * see it immediately. Changes are kept as a draft that only the design mode shows, until they are published.
 * The live preview runs in the browser (designEditor in homepage.js), this component stores the state.
 */
class DesignEditor extends Component
{
    /**
     * @var array{tokens: array<string, string>, elements: array<string, array<string, string>>}
     */
    public array $state = ['tokens' => [], 'elements' => []];

    public bool $hasDraft = false;

    public function mount(): void
    {
        $this->authorizeDesigner();

        $theme = DesignTheme::current();
        $this->state = $theme->working();
        $this->hasDraft = $theme->draft !== null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function saveDraft(array $state): void
    {
        $theme = DesignTheme::current();
        $theme->saveDraft($state, $this->authorizeDesigner());

        $this->state = $theme->working();
        $this->hasDraft = true;

        // The panel is driven by Alpine; re-rendering would close an open color picker of the browser.
        $this->skipRender();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function publish(array $state): void
    {
        DesignTheme::current()->publish($this->authorizeDesigner(), $state, 'Im Design-Modus veröffentlicht');

        $this->dispatch('design-reload');
    }

    public function discard(): void
    {
        $this->authorizeDesigner();

        DesignTheme::current()->discardDraft();

        $this->dispatch('design-reload');
    }

    public function render(): View
    {
        return view('livewire.homepage.design-editor', [
            'roles' => collect(DesignRoles::ROLES)->map(fn (array $role): array => ['label' => $role['label'], 'default' => $role['default']])->all(),
        ]);
    }

    protected function authorizeDesigner(): User
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        abort_unless($user?->can('Homepage:Design'), 403);

        return $user;
    }
}
