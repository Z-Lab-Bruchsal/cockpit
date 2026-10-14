<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CourseTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CourseTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CourseTemplate');
    }

    public function view(AuthUser $authUser, CourseTemplate $courseTemplate): bool
    {
        return $authUser->can('View:CourseTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CourseTemplate');
    }

    public function update(AuthUser $authUser, CourseTemplate $courseTemplate): bool
    {
        return $authUser->can('Update:CourseTemplate');
    }

    public function delete(AuthUser $authUser, CourseTemplate $courseTemplate): bool
    {
        return $authUser->can('Delete:CourseTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CourseTemplate');
    }

    public function restore(AuthUser $authUser, CourseTemplate $courseTemplate): bool
    {
        return $authUser->can('Restore:CourseTemplate');
    }

    public function forceDelete(AuthUser $authUser, CourseTemplate $courseTemplate): bool
    {
        return $authUser->can('ForceDelete:CourseTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CourseTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CourseTemplate');
    }

    public function replicate(AuthUser $authUser, CourseTemplate $courseTemplate): bool
    {
        return $authUser->can('Replicate:CourseTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CourseTemplate');
    }
}
