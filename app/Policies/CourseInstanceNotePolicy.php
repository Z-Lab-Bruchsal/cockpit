<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CourseInstanceNote;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Notes belong to the course instance: whoever may view or edit the instance may read or write its notes.
 */
class CourseInstanceNotePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('View:CourseInstance');
    }

    public function view(AuthUser $authUser, CourseInstanceNote $courseInstanceNote): bool
    {
        return $authUser->can('View:CourseInstance');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Update:CourseInstance');
    }

    public function update(AuthUser $authUser, CourseInstanceNote $courseInstanceNote): bool
    {
        return $authUser->can('Update:CourseInstance');
    }

    public function delete(AuthUser $authUser, CourseInstanceNote $courseInstanceNote): bool
    {
        return $authUser->can('Update:CourseInstance');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Update:CourseInstance');
    }
}
