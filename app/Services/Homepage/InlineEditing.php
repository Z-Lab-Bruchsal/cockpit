<?php

namespace App\Services\Homepage;

use App\Filament\Pages\Inline\EditBlockInline;
use App\Filament\Pages\Inline\EditOfferInline;
use App\Models\CourseInstance;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Editing homepage content in place: cockpit users switch on the edit mode with ?bearbeiten=1 and get a pencil
 * on every block and offer they may change. The pencil opens a small cockpit page in a dialog.
 */
class InlineEditing
{
    public static function user(): ?User
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        return $user;
    }

    /**
     * Whether the current user may edit anything on the homepage, i.e. gets the edit mode switch.
     */
    public static function isAvailable(): bool
    {
        $user = static::user();

        return $user !== null && ($user->can('Update:Section') || $user->can('Update:CourseInstance'));
    }

    public static function isActive(): bool
    {
        return request()->boolean('bearbeiten') && static::isAvailable();
    }

    /**
     * URL of the editor for one block of a section, or null if it cannot be edited here.
     *
     * @param  array<string, mixed>  $block
     */
    public static function blockUrl(?Section $section, array $block): ?string
    {
        $blockId = $block['data']['block_id'] ?? null;

        if (! static::isActive() || $section === null || ! $section->exists || blank($blockId) || ! static::user()->can('update', $section)) {
            return null;
        }

        return EditBlockInline::getUrl(['section' => $section->id, 'block' => $blockId]);
    }

    public static function offerUrl(CourseInstance $instance): ?string
    {
        if (! static::isActive() || ! $instance->exists || ! static::user()->can('update', $instance)) {
            return null;
        }

        return EditOfferInline::getUrl(['instance' => $instance->id]);
    }
}
