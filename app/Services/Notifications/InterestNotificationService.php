<?php

namespace App\Services\Notifications;

use App\Enums\CourseInstanceStatus;
use App\Mail\CourseDigestMail;
use App\Models\Contact;
use App\Models\CourseInstance;
use App\Models\CourseInstanceNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Informs parents about new course instances that fit their children's interests and age.
 *
 * Courses are queued per contact and sent as one digest mail once no further course was queued for
 * the contact's digest time (company default, overridable per contact), respecting an optional weekly maximum.
 * Only contacts who gave consent and confirmed their address are considered, each at most once per instance.
 */
class InterestNotificationService
{
    /**
     * Default filters taken from the course: its interests and age range.
     *
     * @return array{interest_ids: list<int>, min_age: ?int, max_age: ?int}
     */
    public function defaultFilters(CourseInstance $instance): array
    {
        return [
            'interest_ids' => $instance->course->interests()->pluck('interests.id')->all(),
            'min_age' => $instance->course->min_age,
            'max_age' => $instance->course->max_age,
        ];
    }

    /**
     * Contacts to notify, each with the matching children loaded as "kids".
     * Children without birthdate are not filtered by age; their age is checked when they are registered.
     *
     * @param  list<int|string>  $interestIds  children need at least one of these interests; empty = any child
     * @return Collection<int, Contact>
     */
    public function recipients(CourseInstance $instance, array $interestIds, ?int $minAge, ?int $maxAge, bool $includeAlreadyNotified = false): Collection
    {
        $interestIds = array_map('intval', $interestIds);
        $firstDate = $instance->firstDate?->starts_at ?? now();

        // Used for whereHas (Builder) and for eager loading the matching kids (relation).
        $kidFilter = function (Builder|BelongsToMany $kids) use ($interestIds, $minAge, $maxAge, $firstDate): void {
            $kids
                ->when($interestIds !== [], fn ($query) => $query->whereHas('interests', fn (Builder $interests) => $interests->whereIn('interests.id', $interestIds)))
                ->when($minAge !== null || $maxAge !== null, fn ($query) => $query->where(fn ($age) => $age
                    ->whereNull('birthdate')
                    // Age at the first date: born after (first date - max age - 1 year) and on or before (first date - min age).
                    ->orWhere(fn ($range) => $range
                        ->when($minAge !== null, fn ($query) => $query->whereDate('birthdate', '<=', $firstDate->copy()->subYears($minAge)))
                        ->when($maxAge !== null, fn ($query) => $query->whereDate('birthdate', '>', $firstDate->copy()->subYears($maxAge + 1))))));
        };

        return Contact::query()
            ->whereNotNull('interest_mails_consent_at')
            ->whereNotNull('email_verified_at')
            ->whereNotNull('email')
            ->whereHas('kids', $kidFilter)
            ->when(! $includeAlreadyNotified, fn (Builder $query) => $query->whereDoesntHave('courseInstanceNotifications', fn (Builder $notifications) => $notifications->where('course_instance_id', $instance->id)))
            ->with(['kids' => $kidFilter])
            ->orderBy('id')
            ->get();
    }

    /**
     * Queues the instance for the next digest mail of every matching contact.
     *
     * @param  list<int|string>  $interestIds
     * @return int number of contacts the course was queued for
     */
    public function notify(CourseInstance $instance, array $interestIds, ?int $minAge, ?int $maxAge, ?User $sentBy = null): int
    {
        $recipients = $this->recipients($instance, $interestIds, $minAge, $maxAge);

        foreach ($recipients as $contact) {
            CourseInstanceNotification::query()->create([
                'course_instance_id' => $instance->id,
                'contact_id' => $contact->id,
                'sent_by' => $sentBy?->id,
                'matching_kid_ids' => $contact->kids->pluck('id')->all(),
            ]);
        }

        return $recipients->count();
    }

    /**
     * Sends a digest to every contact whose collected courses are due: no new course for the digest time
     * and the weekly maximum not reached. Courses that are no longer bookable are dropped.
     *
     * @return int number of digest mails sent
     */
    public function sendDueDigests(): int
    {
        $sent = 0;

        $contactIds = CourseInstanceNotification::query()->pending()->distinct()->pluck('contact_id');

        foreach (Contact::query()->whereIn('id', $contactIds)->get() as $contact) {
            if ($this->sendDigestIfDue($contact)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function sendDigestIfDue(Contact $contact): bool
    {
        $pending = $contact->courseInstanceNotifications()->pending()->with(['courseInstance.course', 'courseInstance.dates'])->get();

        if ($pending->isEmpty()) {
            return false;
        }

        // Consent withdrawn or address no longer confirmed: drop what was collected.
        if (! $contact->wantsInterestMails() || ! $contact->hasVerifiedEmail() || blank($contact->email)) {
            CourseInstanceNotification::query()->whereKey($pending->modelKeys())->delete();

            return false;
        }

        if ($pending->max('created_at')->copy()->addHours($contact->effectiveDigestHours())->isFuture()) {
            return false;
        }

        if ($this->weeklyMaximumReached($contact)) {
            return false;
        }

        [$bookable, $outdated] = $pending->partition(fn (CourseInstanceNotification $notification): bool => $this->isStillBookable($notification->courseInstance));

        if ($outdated->isNotEmpty()) {
            CourseInstanceNotification::query()->whereKey($outdated->modelKeys())->delete();
        }

        if ($bookable->isEmpty()) {
            return false;
        }

        DB::transaction(function () use ($contact, $bookable): void {
            $digest = $contact->courseDigests()->create(['course_count' => $bookable->count(), 'sent_at' => now()]);

            CourseInstanceNotification::query()->whereKey($bookable->modelKeys())->update(['course_digest_id' => $digest->id, 'sent_at' => now()]);
        });

        Mail::to($contact->email)->queue(new CourseDigestMail($contact, $bookable->modelKeys()));

        return true;
    }

    public function weeklyMaximumReached(Contact $contact): bool
    {
        if ($contact->max_course_mails_per_week === null) {
            return false;
        }

        return $contact->courseDigests()->where('sent_at', '>', now()->subWeek())->count() >= $contact->max_course_mails_per_week;
    }

    protected function isStillBookable(?CourseInstance $instance): bool
    {
        return $instance !== null
            && $instance->status === CourseInstanceStatus::Published
            && $instance->course?->is_published
            && $instance->dates->contains(fn ($date): bool => $date->ends_at->isFuture());
    }
}
