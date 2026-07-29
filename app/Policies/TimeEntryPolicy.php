<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TimeEntry;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimeEntryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TimeEntry');
    }

    public function view(AuthUser $authUser, TimeEntry $timeEntry): bool
    {
        return $authUser->can('View:TimeEntry');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TimeEntry');
    }

    public function update(AuthUser $authUser, TimeEntry $timeEntry): bool
    {
        return $authUser->can('Update:TimeEntry');
    }

    public function delete(AuthUser $authUser, TimeEntry $timeEntry): bool
    {
        return $authUser->can('Delete:TimeEntry');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TimeEntry');
    }

    public function restore(AuthUser $authUser, TimeEntry $timeEntry): bool
    {
        return $authUser->can('Restore:TimeEntry');
    }

    public function forceDelete(AuthUser $authUser, TimeEntry $timeEntry): bool
    {
        return $authUser->can('ForceDelete:TimeEntry');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TimeEntry');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TimeEntry');
    }

    public function replicate(AuthUser $authUser, TimeEntry $timeEntry): bool
    {
        return $authUser->can('Replicate:TimeEntry');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TimeEntry');
    }

}