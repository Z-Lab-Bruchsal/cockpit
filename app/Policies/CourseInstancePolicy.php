<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CourseInstance;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CourseInstancePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CourseInstance');
    }

    public function view(AuthUser $authUser, CourseInstance $courseInstance): bool
    {
        return $authUser->can('View:CourseInstance');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CourseInstance');
    }

    public function update(AuthUser $authUser, CourseInstance $courseInstance): bool
    {
        return $authUser->can('Update:CourseInstance');
    }

    public function delete(AuthUser $authUser, CourseInstance $courseInstance): bool
    {
        return $authUser->can('Delete:CourseInstance');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CourseInstance');
    }

    public function restore(AuthUser $authUser, CourseInstance $courseInstance): bool
    {
        return $authUser->can('Restore:CourseInstance');
    }

    public function forceDelete(AuthUser $authUser, CourseInstance $courseInstance): bool
    {
        return $authUser->can('ForceDelete:CourseInstance');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CourseInstance');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CourseInstance');
    }

    public function replicate(AuthUser $authUser, CourseInstance $courseInstance): bool
    {
        return $authUser->can('Replicate:CourseInstance');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CourseInstance');
    }
}
