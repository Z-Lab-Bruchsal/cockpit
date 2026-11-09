<?php

namespace App\Models;

use App\Services\Homepage\DesignRoles;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Homepage colors, a single row. Brand and accent color live in the company settings (see DesignRoles),
 * all other roles here. The design mode works on a draft that only its author sees until it is published.
 *
 * A theme state is always shaped like `['tokens' => [role => hex], 'elements' => [elementId => [role => hex]]]`.
 */
#[Fillable(['tokens', 'element_overrides', 'draft', 'draft_user_id'])]
class DesignTheme extends Model
{
    /**
     * Ids of homepage elements that may get their own colors, e.g. "tile:3f…" or "offer:12".
     */
    public const ELEMENT_ID_PATTERN = '/^(tile|block|offer):[A-Za-z0-9-]{1,64}$/';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'element_overrides' => 'array',
            'draft' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function draftUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'draft_user_id');
    }

    /**
     * The published state.
     *
     * @return array{tokens: array<string, string>, elements: array<string, array<string, string>>}
     */
    public function published(): array
    {
        $settings = CompanySetting::current();
        $tokens = $this->tokens ?? [];

        foreach (DesignRoles::COMPANY_SETTING_ROLES as $role => $column) {
            $tokens[$role] = $settings->{$column};
        }

        return static::sanitize(['tokens' => $tokens, 'elements' => $this->element_overrides ?? []]);
    }

    /**
     * The draft if there is one, else the published state.
     *
     * @return array{tokens: array<string, string>, elements: array<string, array<string, string>>}
     */
    public function working(): array
    {
        return $this->draft !== null ? static::sanitize($this->draft) : $this->published();
    }

    /**
     * Theme state used for rendering the current request: the draft in the design mode, else the published one.
     *
     * @return array{tokens: array<string, string>, elements: array<string, array<string, string>>}
     */
    public static function forRequest(): array
    {
        $key = 'design-theme-state';

        if (! request()->attributes->has($key)) {
            $theme = static::current();
            request()->attributes->set($key, static::isDesignModeActive() ? $theme->working() : $theme->published());
        }

        return request()->attributes->get($key);
    }

    public static function isDesignModeActive(): bool
    {
        return request()->boolean('design') && static::userMayDesign();
    }

    public static function userMayDesign(): bool
    {
        return (bool) auth('web')->user()?->can('Homepage:Design');
    }

    /**
     * Custom properties for the <html> element.
     *
     * @return array<string, string>
     */
    public static function rootCssVariables(): array
    {
        $variables = [];

        foreach (static::forRequest()['tokens'] as $role => $color) {
            $variables = [...$variables, ...DesignRoles::cssVariables($role, $color)];
        }

        // Without a brand color the homepage keeps the amber fallback of the stylesheet.
        return $variables;
    }

    /**
     * Inline style with the colors a single element overrides, e.g. one tile.
     */
    public static function elementStyle(string $elementId): string
    {
        $variables = [];

        foreach (static::forRequest()['elements'][$elementId] ?? [] as $role => $color) {
            $variables = [...$variables, ...DesignRoles::cssVariables($role, $color)];
        }

        return DesignRoles::style($variables);
    }

    /**
     * Keeps only known roles with hex colors and valid element ids.
     *
     * @param  array<string, mixed>  $state
     * @return array{tokens: array<string, string>, elements: array<string, array<string, string>>}
     */
    public static function sanitize(array $state): array
    {
        $tokens = collect(is_array($state['tokens'] ?? null) ? $state['tokens'] : [])
            ->filter(fn (mixed $color, mixed $role): bool => is_string($role) && DesignRoles::exists($role) && DesignRoles::isHexColor($color))
            ->map(fn (string $color): string => Str::lower($color))
            ->all();

        $elements = collect(is_array($state['elements'] ?? null) ? $state['elements'] : [])
            ->filter(fn (mixed $roles, mixed $elementId): bool => is_string($elementId) && preg_match(static::ELEMENT_ID_PATTERN, $elementId) === 1 && is_array($roles))
            ->map(fn (array $roles): array => static::sanitize(['tokens' => $roles])['tokens'])
            ->filter()
            ->all();

        return ['tokens' => $tokens, 'elements' => $elements];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function saveDraft(array $state, User $user): void
    {
        $this->update(['draft' => static::sanitize($state), 'draft_user_id' => $user->id]);
    }

    public function discardDraft(): void
    {
        $this->update(['draft' => null, 'draft_user_id' => null]);
    }

    /**
     * Makes the given state (default: the draft) the published one and remembers the previous state as revision.
     *
     * @param  array<string, mixed>|null  $state
     */
    public function publish(User $user, ?array $state = null, ?string $note = null): void
    {
        $state = static::sanitize($state ?? $this->draft ?? $this->published());

        DB::transaction(function () use ($state, $user, $note): void {
            DesignThemeRevision::query()->create([
                'snapshot' => $this->published(),
                'user_id' => $user->id,
                'note' => $note,
            ]);

            $settings = CompanySetting::current();

            foreach (DesignRoles::COMPANY_SETTING_ROLES as $role => $column) {
                $settings->{$column} = $state['tokens'][$role] ?? DesignRoles::ROLES[$role]['default'];
            }

            $settings->save();

            $this->update([
                'tokens' => collect($state['tokens'])->except(array_keys(DesignRoles::COMPANY_SETTING_ROLES))->all(),
                'element_overrides' => $state['elements'],
                'draft' => null,
                'draft_user_id' => null,
            ]);
        });
    }

    public function restore(DesignThemeRevision $revision, User $user): void
    {
        $this->publish($user, $revision->snapshot, 'Wiederhergestellt: Version vom '.$revision->created_at->format('d.m.Y H:i'));
    }
}
