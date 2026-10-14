<?php

namespace App\Models;

use Database\Factories\CourseInstanceNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal note on a course instance, e.g. from a phone call with the school about directions or special needs.
 */
#[Fillable(['course_instance_id', 'registration_id', 'user_id', 'body'])]
class CourseInstanceNote extends Model
{
    /** @use HasFactory<CourseInstanceNoteFactory> */
    use HasFactory;

    public function courseInstance(): BelongsTo
    {
        return $this->belongsTo(CourseInstance::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
