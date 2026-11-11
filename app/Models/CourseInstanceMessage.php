<?php

namespace App\Models;

use Database\Factories\CourseInstanceMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mail sent from the cockpit to the participants of a course instance.
 * The recipients are stored as they were at sending time.
 *
 * @property list<array{registration_id: int, email: string, name: ?string}> $recipients
 */
#[Fillable(['course_instance_id', 'user_id', 'subject', 'body', 'recipients'])]
class CourseInstanceMessage extends Model
{
    /** @use HasFactory<CourseInstanceMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipients' => 'array',
        ];
    }

    public function courseInstance(): BelongsTo
    {
        return $this->belongsTo(CourseInstance::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Recipients for display, e.g. "Maria <maria@example.com>".
     *
     * @return list<string>
     */
    public function recipientLabels(): array
    {
        return array_map(
            fn (array $recipient): string => filled($recipient['name'] ?? null) ? "{$recipient['name']} <{$recipient['email']}>" : $recipient['email'],
            $this->recipients ?? [],
        );
    }
}
