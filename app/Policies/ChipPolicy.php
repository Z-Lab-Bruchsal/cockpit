<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Chip;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChipPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Chip');
    }

    public function view(AuthUser $authUser, Chip $chip): bool
    {
        return $authUser->can('View:Chip');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Chip');
    }

    public function update(AuthUser $authUser, Chip $chip): bool
    {
        return $authUser->can('Update:Chip');
    }

    public function delete(AuthUser $authUser, Chip $chip): bool
    {
        return $authUser->can('Delete:Chip');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Chip');
    }

    public function restore(AuthUser $authUser, Chip $chip): bool
    {
        return $authUser->can('Restore:Chip');
    }

    public function forceDelete(AuthUser $authUser, Chip $chip): bool
    {
        return $authUser->can('ForceDelete:Chip');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Chip');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Chip');
    }

    public function replicate(AuthUser $authUser, Chip $chip): bool
    {
        return $authUser->can('Replicate:Chip');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Chip');
    }

}