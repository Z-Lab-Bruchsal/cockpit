<?php

namespace App\Services\Registrations;

use App\Enums\EnrollmentStatus;
use App\Mail\ParticipantMessageMail;
use App\Models\CourseInstance;
use App\Models\CourseInstanceMessage;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a mail written in the cockpit to the participants of a course instance and keeps it in the instance's history.
 * Every address gets the mail once, no matter how many children of the family take part.
 */
class ParticipantMessageService
{
    /**
     * Statuses that can be chosen as recipients; cancelled, expired and merely held seats are not participants.
     *
     * @return list<EnrollmentStatus>
     */
    public static function selectableStatuses(): array
    {
        return array_values(array_filter(
            EnrollmentStatus::cases(),
            fn (EnrollmentStatus $status): bool => ! in_array($status, [EnrollmentStatus::Held, EnrollmentStatus::Cancelled, EnrollmentStatus::Expired], true),
        ));
    }

    /**
     * Registrations with a mail address and at least one enrollment in the given statuses
     * (or, if given, among the given enrollments), one per address.
     *
     * @param  array<int, EnrollmentStatus|string>  $statuses
     * @param  array<int, int|string>|null  $enrollmentIds
     * @return Collection<int, Registration>
     */
    public function recipients(CourseInstance $instance, array $statuses, ?array $enrollmentIds = null): Collection
    {
        $allowedStatuses = array_map(fn (EnrollmentStatus $status): string => $status->value, self::selectableStatuses());

        $statuses = array_values(array_intersect(
            array_map(fn (EnrollmentStatus|string $status): string => $status instanceof EnrollmentStatus ? $status->value : $status, $statuses),
            $allowedStatuses,
        ));

        if ($enrollmentIds === null && $statuses === []) {
            return collect();
        }

        return $instance->registrations()
            ->with('contact')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                ->where('course_instance_id', $instance->id)
                ->when($enrollmentIds !== null,
                    fn (Builder $query) => $query->whereKey($enrollmentIds)->whereIn('status', $allowedStatuses),
                    fn (Builder $query) => $query->whereIn('status', $statuses)))
            ->orderBy('id')
            ->get()
            ->unique(fn (Registration $registration): string => mb_strtolower(trim($registration->email)))
            ->values()
            ->toBase();
    }

    /**
     * Queues the mail for every recipient and stores it in the history.
     *
     * @param  Collection<int, Registration>  $recipients
     */
    public function send(CourseInstance $instance, string $subject, string $body, Collection $recipients, ?User $sender = null): CourseInstanceMessage
    {
        $message = $instance->participantMessages()->create([
            'user_id' => $sender?->id,
            'subject' => $subject,
            'body' => $body,
            'recipients' => $recipients->map(fn (Registration $registration): array => [
                'registration_id' => $registration->id,
                'email' => $registration->email,
                'name' => $registration->contact?->name ?? $registration->contact_name,
            ])->values()->all(),
        ]);

        foreach ($recipients as $registration) {
            Mail::to($registration->email)->queue(new ParticipantMessageMail($message, $registration));
        }

        return $message;
    }
}
