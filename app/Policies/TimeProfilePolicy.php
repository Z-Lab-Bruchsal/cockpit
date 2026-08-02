<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TimeProfile;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TimeProfilePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TimeProfile');
    }

    public function view(AuthUser $authUser, TimeProfile $timeProfile): bool
    {
        return $authUser->can('View:TimeProfile');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TimeProfile');
    }

    public function update(AuthUser $authUser, TimeProfile $timeProfile): bool
    {
        return $authUser->can('Update:TimeProfile');
    }

    public function delete(AuthUser $authUser, TimeProfile $timeProfile): bool
    {
        return $authUser->can('Delete:TimeProfile');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TimeProfile');
    }

    public function restore(AuthUser $authUser, TimeProfile $timeProfile): bool
    {
        return $authUser->can('Restore:TimeProfile');
    }

    public function forceDelete(AuthUser $authUser, TimeProfile $timeProfile): bool
    {
        return $authUser->can('ForceDelete:TimeProfile');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TimeProfile');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TimeProfile');
    }

    public function replicate(AuthUser $authUser, TimeProfile $timeProfile): bool
    {
        return $authUser->can('Replicate:TimeProfile');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TimeProfile');
    }
}
