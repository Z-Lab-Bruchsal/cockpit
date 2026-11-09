<?php

namespace App\Services\Homepage;

use App\Models\CompanySetting;

/**
 * Color roles of the homepage that can be changed in the design mode and on the cockpit page "Design".
 * Each role is a CSS custom property, see resources/css/homepage.css. Elements on the homepage name their roles
 * in `data-design-role`; elements with `data-design-element` may override a role just for themselves.
 */
class DesignRoles
{
    /**
     * @var array<string, array{label: string, group: string, default: string}>
     */
    public const ROLES = [
        'brand' => ['label' => 'Hauptfarbe', 'group' => 'Grundfarben', 'default' => '#e41f6c'],
        'accent' => ['label' => 'Akzentfarbe (heller Hintergrund)', 'group' => 'Grundfarben', 'default' => '#fff7ed'],
        'page-bg' => ['label' => 'Seitenhintergrund', 'group' => 'Grundfarben', 'default' => '#f1eff4'],
        'ink' => ['label' => 'Textfarbe', 'group' => 'Grundfarben', 'default' => '#25252b'],
        'card-bg' => ['label' => 'Hintergrund von Karten und Kacheln', 'group' => 'Grundfarben', 'default' => '#ffffff'],
        'nav-link' => ['label' => 'Links in der Kopfzeile', 'group' => 'Kopfzeile', 'default' => '#e41f6c'],
        'ribbon-newsletter' => ['label' => 'Band „Newsletter“', 'group' => 'Kopfzeile', 'default' => '#f49b58'],
        'ribbon-donation' => ['label' => 'Band „Spenden“', 'group' => 'Kopfzeile', 'default' => '#e41f6c'],
        'tile-heading' => ['label' => 'Überschrift von Kacheln', 'group' => 'Kacheln', 'default' => '#e41f6c'],
        'tile-highlight' => ['label' => 'Hervorgehobene Kachel', 'group' => 'Kacheln', 'default' => '#e41f6c'],
        'hero-eyebrow' => ['label' => 'Dachzeile großer Kacheln', 'group' => 'Kacheln', 'default' => '#c41b5d'],
        'hero-heading' => ['label' => 'Überschrift großer Kacheln', 'group' => 'Kacheln', 'default' => '#e41f6c'],
        'button-ink' => ['label' => 'Text und Rahmen von Buttons', 'group' => 'Buttons', 'default' => '#c41b5d'],
        'offer-featured-bg' => ['label' => 'Hintergrund großer Angebotskarten', 'group' => 'Angebote', 'default' => '#fff7ed'],
        'label-border' => ['label' => 'Rahmen des Etiketts', 'group' => 'Angebote', 'default' => '#e41f6c'],
        'label-outline' => ['label' => 'Äußerer Rahmen des Etiketts', 'group' => 'Angebote', 'default' => '#f49b58'],
        'footer-bg' => ['label' => 'Hintergrund Fußzeile', 'group' => 'Fußzeile', 'default' => '#302a31'],
        'footer-ink' => ['label' => 'Text Fußzeile', 'group' => 'Fußzeile', 'default' => '#f6f2f5'],
        'footer-hover' => ['label' => 'Links Fußzeile (Hover)', 'group' => 'Fußzeile', 'default' => '#ff94b9'],
    ];

    /**
     * Roles stored in the company settings instead of the design theme (also used by course templates and mails).
     *
     * @var array<string, string>
     */
    public const COMPANY_SETTING_ROLES = ['brand' => 'primary_color', 'accent' => 'secondary_color'];

    public static function exists(string $role): bool
    {
        return array_key_exists($role, static::ROLES);
    }

    public static function label(string $role): string
    {
        return static::ROLES[$role]['label'] ?? $role;
    }

    /**
     * @return array<string, array<string, string>> group => [role => label]
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (static::ROLES as $role => $definition) {
            $groups[$definition['group']][$role] = $definition['label'];
        }

        return $groups;
    }

    public static function isHexColor(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1;
    }

    /**
     * CSS custom properties for one role. The brand and accent colors also set the text colors derived from them.
     *
     * @return array<string, string>
     */
    public static function cssVariables(string $role, string $color): array
    {
        return match ($role) {
            'brand' => CompanySetting::brandCssVariables($color),
            'accent' => ['--accent' => $color, '--accent-contrast' => CompanySetting::contrastTextColor($color)],
            default => ['--'.$role => $color],
        };
    }

    /**
     * @param  array<string, string>  $variables
     */
    public static function style(array $variables): string
    {
        return collect($variables)->map(fn (string $value, string $name): string => $name.': '.$value)->implode('; ');
    }
}
