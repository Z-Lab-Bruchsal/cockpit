<?php

namespace App\Services\Homepage;

use App\Filament\Pages\Inline\EditOfferInline;
use App\Filament\Pages\Inline\EditTileInline;
use App\Models\CourseInstance;
use App\Models\Section;
use App\Models\Tile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Editing homepage content in place: cockpit users switch on the edit mode with ?bearbeiten=1 and get a pencil
 * on every tile and offer they may change, which opens a small cockpit page in a dialog. Users who may arrange
 * tiles or the footer get the drag & drop editors for them in the same mode.
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
     * Whether the current user may edit anything on the homepage, i.e. gets the edit mode switch:
     * blocks, tiles and offers, arranging tiles or arranging the footer.
     */
    public static function isAvailable(): bool
    {
        $user = static::user();

        return $user !== null && (
            $user->can('Update:Section')
            || $user->can('Update:CourseInstance')
            || $user->can('reorder', Section::class)
            || $user->can('View:CompanySettingsPage')
        );
    }

    /**
     * The edit mode is requested with ?bearbeiten=1; ?anordnen=1 is the former arrange mode, which is part of it now.
     */
    public static function isRequested(): bool
    {
        return request()->boolean('bearbeiten') || request()->boolean('anordnen');
    }

    public static function isActive(): bool
    {
        return static::isRequested() && static::isAvailable();
    }

    /**
     * URL of the editor for one tile, or null if it cannot be edited here.
     */
    public static function tileUrl(Tile $tile): ?string
    {
        if (! static::isActive() || ! $tile->exists || ! static::user()->can('update', $tile)) {
            return null;
        }

        return EditTileInline::getUrl(['tile' => $tile->uuid]);
    }

    public static function offerUrl(CourseInstance $instance): ?string
    {
        if (! static::isActive() || ! $instance->exists || ! static::user()->can('update', $instance)) {
            return null;
        }

        return EditOfferInline::getUrl(['instance' => $instance->id]);
    }
}
