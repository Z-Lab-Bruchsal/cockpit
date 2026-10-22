<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['name', 'street', 'zip', 'city', 'logo_path', 'logo_alt', 'app_title', 'homepage_title', 'homepage_tagline', 'primary_color', 'secondary_color', 'cockpit_primary_color', 'cockpit_success_color', 'cockpit_warning_color', 'cockpit_danger_color', 'cockpit_info_color', 'contact_email', 'contact_phone', 'footer_text', 'footer_links', 'social_links', 'hold_minutes', 'confirmation_hours', 'waitlist_offer_hours', 'booking_limit_per_hour', 'booking_limit_per_day', 'max_seats_per_registration', 'digest_hours', 'school_reminders_enabled', 'teacher_reminder_days', 'staff_reminder_days', 'school_reschedule_deadline_days', 'directions_document_path'])]
class CompanySetting extends Model
{
    /**
     * Social media platforms offered for the homepage footer.
     *
     * @var array<string, string>
     */
    public const SOCIAL_PLATFORMS = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'linkedin' => 'LinkedIn',
        'mastodon' => 'Mastodon',
        'tiktok' => 'TikTok',
        'other' => 'Sonstige',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'hold_minutes' => 15,
        'confirmation_hours' => 48,
        'waitlist_offer_hours' => 48,
        'booking_limit_per_hour' => 2,
        'booking_limit_per_day' => 4,
        'max_seats_per_registration' => 5,
        'digest_hours' => 4,
        'school_reminders_enabled' => true,
        'teacher_reminder_days' => 14,
        'staff_reminder_days' => 21,
        'school_reschedule_deadline_days' => 14,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'footer_links' => 'array',
            'social_links' => 'array',
            'school_reminders_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * Title of the cockpit, also used as sender name of mails.
     */
    public function appTitle(): string
    {
        return $this->app_title ?: ($this->name ?: config('app.name'));
    }

    public function hasBookingLimit(): bool
    {
        return $this->booking_limit_per_hour > 0 || $this->booking_limit_per_day > 0;
    }

    /**
     * Active booking limits per child, each counted over a rolling window (last 60 minutes, last 24 hours).
     *
     * @return list<array{limit: int, minutes: int, text: string}>
     */
    public function bookingLimits(): array
    {
        return array_values(array_filter([
            $this->booking_limit_per_hour > 0
                ? ['limit' => $this->booking_limit_per_hour, 'minutes' => 60, 'text' => static::coursesText($this->booking_limit_per_hour).' pro Stunde']
                : null,
            $this->booking_limit_per_day > 0
                ? ['limit' => $this->booking_limit_per_day, 'minutes' => 1440, 'text' => static::coursesText($this->booking_limit_per_day).' in 24 Stunden']
                : null,
        ]));
    }

    /**
     * The smallest active limit, i.e. how many courses one child can book in a single checkout.
     */
    public function strictestBookingLimit(): ?int
    {
        $limits = array_column($this->bookingLimits(), 'limit');

        return $limits === [] ? null : min($limits);
    }

    /**
     * e.g. "höchstens 2 Kurse pro Stunde und 4 Kurse in 24 Stunden".
     */
    public function bookingLimitText(): string
    {
        return 'höchstens '.implode(' und ', array_column($this->bookingLimits(), 'text'));
    }

    protected static function coursesText(int $count): string
    {
        return $count === 1 ? '1 Kurs' : "{$count} Kurse";
    }

    /**
     * Name shown in the homepage header and browser title.
     */
    public function homepageTitle(): string
    {
        return $this->homepage_title ?: ($this->name ?: config('app.name'));
    }

    /**
     * Makes the configured title the application name (cockpit brand, page titles, mails).
     * Silently skipped while the table does not exist yet, e.g. during the first migration.
     */
    public static function applyToConfig(): void
    {
        $settings = rescue(fn (): ?self => static::query()->find(1), report: false);

        if ($settings === null || blank($settings->app_title)) {
            return;
        }

        config([
            'app.name' => $settings->app_title,
            'mail.from.name' => $settings->app_title,
        ]);
    }

    /**
     * Colors chosen for the cockpit, keyed by Filament color name. Unset colors keep Filament's defaults.
     *
     * @return array<string, string>
     */
    public static function cockpitColors(): array
    {
        $settings = rescue(fn (): ?self => static::query()->find(1), report: false);

        if ($settings === null) {
            return [];
        }

        return collect([
            'primary' => $settings->cockpit_primary_color,
            'success' => $settings->cockpit_success_color,
            'warning' => $settings->cockpit_warning_color,
            'danger' => $settings->cockpit_danger_color,
            'info' => $settings->cockpit_info_color,
        ])
            ->map(fn (?string $color): ?string => Str::sanitizeCssColor($color))
            ->filter()
            ->all();
    }

    /**
     * CSS custom properties for the homepage theme, including readable text colors on top of them.
     *
     * @return array<string, string>
     */
    public function homepageCssVariables(): array
    {
        $primary = Str::sanitizeCssColor($this->primary_color) ?? '#f59e0b';
        $secondary = Str::sanitizeCssColor($this->secondary_color) ?? '#fff7ed';

        return [
            ...static::brandCssVariables($primary),
            '--accent' => $secondary,
            '--accent-contrast' => static::contrastTextColor($secondary),
        ];
    }

    /**
     * The brand color plus the text colors derived from it: on top of it (contrast), for headings
     * on white (at least 3:1, large text) and for small text on white (14 % darker, at least 4.5:1).
     *
     * @return array{'--brand': string, '--brand-contrast': string, '--brand-heading': string, '--brand-ink': string}
     */
    public static function brandCssVariables(string $color): array
    {
        return [
            '--brand' => $color,
            '--brand-contrast' => static::contrastTextColor($color),
            '--brand-heading' => static::readableOnWhite($color, 3.0),
            '--brand-ink' => static::readableOnWhite(static::darken($color, 0.86)),
        ];
    }

    /**
     * Black or white, whichever is more readable on the given hex background (WCAG relative luminance).
     */
    public static function contrastTextColor(string $hexColor): string
    {
        $luminance = static::relativeLuminance($hexColor);

        if ($luminance === null) {
            return '#111827';
        }

        // Contrast against white vs. against near-black (#111827, luminance ~0.0089).
        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.0589) ? '#ffffff' : '#111827';
    }

    /**
     * The given hex color, darkened until it reaches the WCAG contrast as text on white
     * (4.5:1 for normal text, 3:1 for large text). Dark enough colors and non-hex values are returned unchanged.
     */
    public static function readableOnWhite(string $hexColor, float $minContrast = 4.5): string
    {
        $color = $hexColor;

        if (static::hexChannels($color) === null) {
            return $color;
        }

        while (1.05 / (static::relativeLuminance($color) + 0.05) < $minContrast) {
            $color = static::darken($color, 0.9);
        }

        return $color;
    }

    /**
     * The given hex color with all channels multiplied by the factor (0–1); non-hex values are returned unchanged.
     */
    public static function darken(string $hexColor, float $factor): string
    {
        $channels = static::hexChannels($hexColor);

        if ($channels === null) {
            return $hexColor;
        }

        return vsprintf('#%02x%02x%02x', array_map(fn (int $channel): int => (int) round($channel * $factor), $channels));
    }

    /**
     * WCAG relative luminance (0–1) of a hex color, or null if it is not a hex color.
     */
    public static function relativeLuminance(string $hexColor): ?float
    {
        $channels = static::hexChannels($hexColor);

        if ($channels === null) {
            return null;
        }

        [$red, $green, $blue] = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /**
     * RGB channels (0–255) of a #rgb, #rgba, #rrggbb or #rrggbbaa color; alpha is ignored.
     *
     * @return array{int, int, int}|null
     */
    protected static function hexChannels(string $hexColor): ?array
    {
        $hex = ltrim($hexColor, '#');

        if (strlen($hex) === 3 || strlen($hex) === 4) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}/', $hex)) {
            return null;
        }

        return array_map(fn (string $channel): int => (int) hexdec($channel), str_split(substr($hex, 0, 6), 2));
    }

    /**
     * Footer links with resolved urls; links to hidden or deleted sections are skipped.
     *
     * @return list<array{label: string, url: string, external: bool}>
     */
    public function resolvedFooterLinks(): array
    {
        $links = collect($this->footer_links ?? []);
        $sections = Section::query()
            ->whereIn('id', $links->pluck('section_id')->filter())
            ->get()
            ->keyBy('id');

        return $links
            ->map(function (array $link) use ($sections): ?array {
                $section = $sections->get($link['section_id'] ?? null);

                if ($section !== null) {
                    return $section->isPubliclyVisible()
                        ? ['label' => $link['label'], 'url' => $section->url(), 'external' => false]
                        : null;
                }

                $url = Str::sanitizeUrl($link['url'] ?? null);

                return filled($url) ? ['label' => $link['label'], 'url' => $url, 'external' => true] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function resolvedSocialLinks(): array
    {
        return collect($this->social_links ?? [])
            ->map(fn (array $link): ?array => filled($url = Str::sanitizeUrl($link['url'] ?? null))
                ? ['label' => self::SOCIAL_PLATFORMS[$link['platform'] ?? 'other'] ?? 'Link', 'url' => $url]
                : null)
            ->filter()
            ->values()
            ->all();
    }

    public function copyrightText(): string
    {
        return '© '.now()->year.' '.($this->footer_text ?: ($this->name ?: $this->homepageTitle()));
    }
}
