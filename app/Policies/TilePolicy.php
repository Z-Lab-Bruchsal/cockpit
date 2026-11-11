<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Section;
use App\Models\Tile;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Tiles are the content of a section, so whoever may edit the section may edit its tiles.
 */
class TilePolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('viewAny', Section::class);
    }

    public function view(AuthUser $authUser, Tile $tile): bool
    {
        return $authUser->can('view', $tile->section);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Section');
    }

    public function update(AuthUser $authUser, Tile $tile): bool
    {
        return $authUser->can('update', $tile->section);
    }

    public function delete(AuthUser $authUser, Tile $tile): bool
    {
        return $authUser->can('update', $tile->section);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Section');
    }

    public function replicate(AuthUser $authUser, Tile $tile): bool
    {
        return $authUser->can('update', $tile->section);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Section');
    }
}
