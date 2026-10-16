<?php

namespace App\Services\Schools;

use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SchoolType;
use App\Enums\SchoolVenue;
use App\Exceptions\ReservationException;
use App\Mail\SchoolBookingUpdateMail;
use App\Mail\SchoolRequestDecidedMail;
use App\Mail\SchoolRequestMail;
use App\Mail\SchoolRequestReceivedMail;
use App\Mail\SchoolVisitReminderMail;
use App\Mail\SchoolVisitStaffReminderMail;
use App\Models\Contact;
use App\Models\CourseInstance;
use App\Models\CourseInstanceNote;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\CourseReservationService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Bookings of school classes: a school or one of its teachers reserves a slot (one course instance = one class),
 * the course lead approves or declines every request, reminders go to the teacher and the course lead,
 * and the school can cancel or move the booking until the configured deadline.
 *
 * Seats are handled by CourseReservationService; this service adds the school specific states and mails.
 */
class SchoolBookingService
{
    public function __construct(protected CourseReservationService $reservations) {}

    /**
     * Starts the booking of a free slot by a logged-in school or teacher.
     *
     * @return array{0: Registration, 1: string} the held registration and the plain token of the booking page
     */
    public function hold(CourseInstance $instance, Contact $booker, ?Registration $replaces = null): array
    {
        if (! $instance->course?->isForSchoolClasses()) {
            throw new ReservationException('Dieser Kurs ist nicht für Schulklassen gedacht.');
        }

        if (! $booker->canBookForSchool()) {
            throw new ReservationException('Bitte melden Sie sich mit dem Konto Ihrer Schule an.');
        }

        [$registration, $plainToken] = $this->reservations->hold($instance, 1, $booker);

        if ($replaces !== null) {
            $registration->update([
                'replaces_registration_id' => $replaces->id,
                'school_class_data' => $replaces->school_class_data,
                'venue' => in_array($replaces->venue, $instance->allowedVenues(), true) ? $replaces->venue : null,
            ]);
        }

        return [$registration, $plainToken];
    }

    /**
     * Sends the held slot as request to the course lead. No double opt-in: the booker is logged in.
     *
     * @param  array{class_name: string, grade: int|string, school_type: string, students: int|string, teacher_name: string, teacher_phone: string, topic?: ?string, venue?: ?string}  $class
     */
    public function checkout(Registration $registration, array $class, Contact $booker): Registration
    {
        $school = $booker->schoolAccount();

        if ($school === null) {
            throw new ReservationException('Bitte melden Sie sich mit dem Konto Ihrer Schule an.');
        }

        DB::transaction(function () use ($registration, $class, $booker, $school): void {
            $registration = Registration::query()->with(['enrollments', 'courseInstance.course'])->lockForUpdate()->find($registration->id);

            if ($registration === null || $registration->status !== RegistrationStatus::Held || ! $registration->expires_at?->isFuture()) {
                throw new ReservationException('Ihre Reservierung ist abgelaufen. Der Termin wurde wieder freigegeben – bitte starten Sie die Buchung neu.');
            }

            $instance = $registration->courseInstance;
            $allowedVenues = $instance->allowedVenues();
            $venue = count($allowedVenues) === 1 ? $allowedVenues[0] : SchoolVenue::tryFrom((string) ($class['venue'] ?? ''));
            $schoolType = SchoolType::tryFrom((string) $class['school_type']);
            $maxClassSize = $instance->maxClassSize();

            if (! in_array($venue, $allowedVenues, true)) {
                throw new ReservationException('Bitte wählen Sie den Ort.');
            }

            if (! in_array($schoolType, $instance->course->allowedSchoolTypes(), true) || ! in_array((int) $class['grade'], $instance->course->allowedGrades(), true)) {
                throw new ReservationException('Dieser Kurs ist nicht für diese Schulart oder Klassenstufe gedacht.');
            }

            if ($maxClassSize !== null && (int) $class['students'] > $maxClassSize) {
                throw new ReservationException("Für diesen Kurs sind höchstens {$maxClassSize} Kinder möglich.");
            }

            $teacherPhone = trim($class['teacher_phone']);

            $registration->update([
                'contact_id' => $booker->id,
                'school_id' => $school->id,
                'email' => $booker->email,
                'contact_name' => trim($class['teacher_name']),
                'phone' => $teacherPhone,
                'message' => filled($class['topic'] ?? null) ? trim($class['topic']) : null,
                'school_class_data' => [
                    'class_name' => trim($class['class_name']),
                    'grade' => (int) $class['grade'],
                    'school_type' => $schoolType->value,
                    'students' => (int) $class['students'],
                    'teacher_name' => trim($class['teacher_name']),
                    'teacher_email' => $booker->email,
                    'teacher_phone' => $teacherPhone,
                    'topic' => filled($class['topic'] ?? null) ? trim($class['topic']) : null,
                ],
                'venue' => $venue,
                'status' => RegistrationStatus::Requested,
                'hold_token_hash' => null,
                'checked_out_at' => now(),
                'expires_at' => null,
            ]);

            $registration->enrollments()
                ->where('status', EnrollmentStatus::Held)
                ->update([
                    'kid_name' => 'Klasse '.trim($class['class_name']),
                    'status' => EnrollmentStatus::Requested,
                    'expires_at' => null,
                ]);
        });

        $registration = $this->fresh($registration);

        $this->sendToTeacher($registration, new SchoolRequestReceivedMail(collect([$registration])));
        $this->sendRequestToCourseLead($registration);

        return $registration;
    }

    /**
     * The school type and grade a school entered for its most recent class, to prefill the next booking.
     *
     * @return array{school_type: ?string, grade: ?int}
     */
    public function lastClassDetails(Contact $school): array
    {
        $data = Registration::query()
            ->where('school_id', $school->id)
            ->whereNotNull('school_class_data')
            ->latest('checked_out_at')
            ->value('school_class_data');

        $data = is_string($data) ? json_decode($data, true) : ($data ?? []);

        return [
            'school_type' => $data['school_type'] ?? null,
            'grade' => isset($data['grade']) ? (int) $data['grade'] : null,
        ];
    }

    /**
     * The course lead accepts the request. A moved booking replaces the old one only now.
     */
    public function approve(Registration $registration, ?string $note = null): void
    {
        if (! $registration->awaitsSchoolDecision()) {
            return;
        }

        $replaced = DB::transaction(function () use ($registration, $note): ?Registration {
            $registration->enrollments()
                ->where('status', EnrollmentStatus::Requested)
                ->update(['status' => EnrollmentStatus::Confirmed, 'expires_at' => null]);

            $registration->update([
                'status' => RegistrationStatus::Confirmed,
                'confirmed_at' => now(),
                'approval_decided_at' => now(),
                'decision_note' => filled($note) ? trim($note) : null,
                'staff_remind_at' => $this->initialStaffReminder($registration->courseInstance),
                'staff_reminded_at' => null,
            ]);

            return $registration->replacesRegistration;
        });

        if ($replaced !== null && $replaced->status->isActive()) {
            $this->reservations->cancel($replaced, notify: false);
        }

        $registration = $this->fresh($registration);
        $this->sendToTeacher($registration, new SchoolRequestDecidedMail($registration, approved: true, visitUrl: $this->visitUrl($registration)));
    }

    /**
     * The course lead declines the request and the slot becomes free. When the booking was to be moved,
     * the old date stays as it is.
     */
    public function decline(Registration $registration, ?string $note = null): void
    {
        if (! $registration->awaitsSchoolDecision()) {
            return;
        }

        DB::transaction(function () use ($registration, $note): void {
            $registration->enrollments()
                ->whereNotIn('status', [EnrollmentStatus::Cancelled, EnrollmentStatus::Expired])
                ->update(['status' => EnrollmentStatus::Cancelled, 'cancelled_at' => now(), 'expires_at' => null]);

            $registration->update([
                'status' => RegistrationStatus::Declined,
                'approval_decided_at' => now(),
                'decision_note' => filled($note) ? trim($note) : null,
            ]);
        });

        $registration = $this->fresh($registration);
        $this->sendToTeacher($registration, new SchoolRequestDecidedMail($registration, approved: false));
    }

    /**
     * Cancellation by the school before the deadline. The course lead is always informed.
     */
    public function cancelBySchool(Registration $registration): void
    {
        if (! $this->canBeChangedBySchool($registration)) {
            throw new ReservationException('Dieser Termin kann nicht mehr online storniert werden. Bitte rufen Sie uns an.');
        }

        $this->reservations->cancel($registration, notify: false);

        // A pending move to another date is pointless without the original booking.
        Registration::query()
            ->where('replaces_registration_id', $registration->id)
            ->where('status', RegistrationStatus::Requested)
            ->get()
            ->each(fn (Registration $move) => $this->reservations->cancel($move, notify: false));

        $this->sendToCourseLead($this->fresh($registration), SchoolBookingUpdateMail::CANCELLED);
    }

    /**
     * Cancelling and moving are possible for requested and confirmed bookings until the deadline before the first date.
     */
    public function canBeChangedBySchool(Registration $registration): bool
    {
        return in_array($registration->status, [RegistrationStatus::Requested, RegistrationStatus::Confirmed], true)
            && $registration->courseInstance->canStillBeRescheduled();
    }

    /**
     * Other free slots of the same course a booking can be moved to.
     *
     * @return Collection<int, CourseInstance>
     */
    public function rescheduleOptions(Registration $registration): Collection
    {
        return $registration->courseInstance->course->upcomingInstances()
            ->whereKeyNot($registration->course_instance_id)
            ->with(['dates', 'course'])
            ->get()
            ->filter(fn (CourseInstance $instance): bool => $instance->freeSeats() > 0 && $instance->availability()->acceptsRegistrations())
            ->values();
    }

    /**
     * The teacher confirms that the visit takes place, optionally correcting the number of students and the topic.
     */
    public function confirmVisit(Registration $registration, ?int $students = null, ?string $remark = null): void
    {
        $data = $registration->school_class_data ?? [];
        $changes = [];

        if ($students !== null && $students !== (int) ($data['students'] ?? 0)) {
            $changes[] = 'Schülerzahl: '.($data['students'] ?? '–').' → '.$students;
            $data['students'] = $students;
        }

        if (filled($remark)) {
            $changes[] = 'Anmerkung: '.trim($remark);
            $data['teacher_remark'] = trim($remark);
        }

        $registration->update([
            'school_class_data' => $data,
            'teacher_confirmed_at' => now(),
        ]);

        $this->sendToCourseLead($this->fresh($registration), SchoolBookingUpdateMail::CONFIRMED, $changes);
    }

    /**
     * Sends due reminders to teachers and course leads.
     *
     * @return array{teachers: int, staff: int}
     */
    public function sendDueReminders(): array
    {
        $sent = ['teachers' => 0, 'staff' => 0];

        $registrations = Registration::query()
            ->where('status', RegistrationStatus::Confirmed)
            ->whereNotNull('school_id')
            ->where(fn ($query) => $query->whereNull('teacher_reminded_at')->orWhere(fn ($staff) => $staff
                ->whereNull('staff_reminded_at')
                ->whereNotNull('staff_remind_at')
                ->where('staff_remind_at', '<=', now())))
            ->whereHas('courseInstance.dates', fn ($dates) => $dates->where('starts_at', '>', now()))
            ->with(['courseInstance.course', 'courseInstance.dates', 'school', 'contact'])
            ->get();

        foreach ($registrations as $registration) {
            $instance = $registration->courseInstance;

            if (! $instance->schoolRemindersEnabled()) {
                continue;
            }

            $firstStart = $instance->dates->first()?->starts_at;

            if ($registration->teacher_reminded_at === null && $firstStart !== null && $firstStart->copy()->subDays($instance->teacherReminderDays())->isPast()) {
                $this->sendToTeacher($registration, new SchoolVisitReminderMail($registration, $this->visitUrl($registration)));
                $registration->forceFill(['teacher_reminded_at' => now()])->saveQuietly();
                $sent['teachers']++;
            }

            if ($registration->staff_reminded_at === null && $registration->staff_remind_at?->isPast() && ($recipient = $instance->approvalRecipient()) !== null) {
                Mail::to($recipient)->send(new SchoolVisitStaffReminderMail($this->fresh($registration)));
                $registration->forceFill(['staff_reminded_at' => now()])->saveQuietly();
                $sent['staff']++;
            }
        }

        return $sent;
    }

    /**
     * Moves the course lead's reminder to a later moment, optionally with a note.
     */
    public function snoozeStaffReminder(Registration $registration, Carbon $remindAt, ?string $note = null, ?User $user = null): void
    {
        $registration->update(['staff_remind_at' => $remindAt, 'staff_reminded_at' => null]);

        if (filled($note)) {
            $this->addNote($registration->courseInstance, trim((string) $note), $user, $registration);
        }
    }

    public function addNote(CourseInstance $instance, string $body, ?User $user = null, ?Registration $registration = null): CourseInstanceNote
    {
        return $instance->internalNotes()->create([
            'registration_id' => $registration?->id,
            'user_id' => $user?->id,
            'body' => $body,
        ]);
    }

    /**
     * Signed link to the teacher's page for the visit: confirm, cancel or move. Valid until the last date is over.
     */
    public function visitUrl(Registration $registration): string
    {
        $lastEnd = $registration->courseInstance->dates->last()?->ends_at ?? now()->addYear();

        return URL::temporarySignedRoute('homepage.schools.visit', $lastEnd->copy()->addDay(), ['registration' => $registration->id]);
    }

    protected function initialStaffReminder(CourseInstance $instance): ?Carbon
    {
        $firstStart = $instance->dates()->first()?->starts_at;

        if ($firstStart === null) {
            return null;
        }

        $remindAt = $firstStart->copy()->subDays($instance->staffReminderDays());

        return $remindAt->isPast() ? now() : $remindAt;
    }

    protected function sendRequestToCourseLead(Registration $registration): void
    {
        $recipient = $registration->courseInstance->approvalRecipient();

        if ($recipient === null) {
            return;
        }

        $url = fn (string $decision): string => URL::temporarySignedRoute(
            'approvals.show',
            now()->addDays(30),
            ['registration' => $registration->id, 'entscheidung' => $decision],
        );

        Mail::to($recipient)->send(new SchoolRequestMail(
            registration: $registration,
            approveUrl: $url('freigeben'),
            declineUrl: $url('ablehnen'),
        ));
    }

    /**
     * @param  list<string>  $changes
     */
    protected function sendToCourseLead(Registration $registration, string $kind, array $changes = []): void
    {
        $recipient = $registration->courseInstance->approvalRecipient();

        if ($recipient !== null) {
            Mail::to($recipient)->send(new SchoolBookingUpdateMail($registration, $kind, $changes));
        }
    }

    /**
     * Mails go to the booking teacher, with the school's function address in copy.
     */
    protected function sendToTeacher(Registration $registration, Mailable $mail): void
    {
        $schoolEmail = $registration->school?->email;
        $to = $registration->email ?? $schoolEmail;

        if ($to === null) {
            return;
        }

        $pending = Mail::to($to);

        if ($schoolEmail !== null && $schoolEmail !== $to) {
            $pending->cc($schoolEmail);
        }

        $pending->send($mail);
    }

    protected function fresh(Registration $registration): Registration
    {
        return $registration->fresh(['courseInstance.course', 'courseInstance.dates', 'courseInstance.internalNotes.user', 'school', 'contact', 'replacesRegistration.courseInstance.dates', 'enrollments']);
    }
}
