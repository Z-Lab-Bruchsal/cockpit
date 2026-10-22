<?php

namespace App\Models;

use App\Enums\CourseCardStyle;
use App\Enums\CourseDetailLayout;
use Database\Factories\CourseTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'is_default', 'card_style', 'detail_layout', 'accent_color', 'show_age_range', 'show_interests', 'show_free_seats', 'default_card_image_path', 'default_card_image_alt', 'default_image_path', 'default_image_alt', 'extra_content'])]
class CourseTemplate extends Model
{
    /** @use HasFactory<CourseTemplateFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'card_style' => 'image_top',
        'detail_layout' => 'hero_image',
        'show_age_range' => true,
        'show_interests' => true,
        'show_free_seats' => true,
    ];

    protected static function booted(): void
    {
        static::saved(function (CourseTemplate $template): void {
            if ($template->is_default) {
                static::query()->whereKeyNot($template->getKey())->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'card_style' => CourseCardStyle::class,
            'detail_layout' => CourseDetailLayout::class,
            'show_age_range' => 'boolean',
            'show_interests' => 'boolean',
            'show_free_seats' => 'boolean',
            'extra_content' => 'array',
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * The template marked as default, or an unsaved template with standard settings.
     */
    public static function fallback(): self
    {
        return static::query()->where('is_default', true)->first() ?? new static(['name' => 'Standard']);
    }

    /**
     * Inline style overriding the homepage brand color with the template's accent color.
     */
    public function accentStyle(): string
    {
        $color = Str::sanitizeCssColor($this->accent_color);

        if (! $color) {
            return '';
        }

        return collect(CompanySetting::brandCssVariables($color))
            ->map(fn (string $value, string $name): string => "{$name}: {$value}")
            ->implode('; ');
    }
}
