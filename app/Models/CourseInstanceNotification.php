<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A course instance queued for a contact's next digest mail. Unique per instance and contact,
 * so nobody is told twice about the same course.
 */
#[Fillable(['course_instance_id', 'contact_id', 'sent_by', 'matching_kid_ids', 'course_digest_id', 'sent_at'])]
class CourseInstanceNotification extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'matching_kid_ids' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function courseInstance(): BelongsTo
    {
        return $this->belongsTo(CourseInstance::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function digest(): BelongsTo
    {
        return $this->belongsTo(CourseDigest::class, 'course_digest_id');
    }

    /**
     * @param  Builder<CourseInstanceNotification>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('sent_at');
    }
}
