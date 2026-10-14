<?php

namespace App\Mail;

use App\Mail\Concerns\SendsAsHomepage;
use App\Models\Contact;
use App\Models\CourseInstance;
use App\Models\CourseInstanceNotification;
use App\Models\Kid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Digest of new courses for a family, listing for each course which children it fits.
 * Carries a one-click unsubscribe link (RFC 8058) besides the link in the footer.
 */
class CourseDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SendsAsHomepage, SerializesModels;

    /**
     * @param  list<int>  $notificationIds
     */
    public function __construct(
        public Contact $contact,
        public array $notificationIds,
    ) {}

    public function envelope(): Envelope
    {
        $notifications = $this->notifications();

        return new Envelope(
            from: $this->homepageSender(),
            subject: $notifications->count() === 1
                ? 'Neuer Kurs: '.$notifications->first()->courseInstance->displayTitle()
                : $notifications->count().' neue Kurse für '.($this->kidNames()->count() === 1 ? $this->kidNames()->first() : 'eure Kinder'),
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.course-digest',
            with: [
                'items' => $this->items(),
                'unsubscribeUrl' => $this->unsubscribeUrl(),
                'settingsUrl' => route('homepage.account').'#daten',
            ],
        );
    }

    public function unsubscribeUrl(): string
    {
        return route('homepage.unsubscribe', $this->contact->unsubscribe_token);
    }

    /**
     * One entry per course with the children it fits, e.g. ["Lena (11)", "Paul"].
     *
     * @return Collection<int, array{instance: CourseInstance, kids: list<string>}>
     */
    public function items(): Collection
    {
        $kids = Kid::query()->whereIn('id', $this->notifications()->pluck('matching_kid_ids')->flatten()->unique())->get()->keyBy('id');

        return $this->notifications()->map(function (CourseInstanceNotification $notification) use ($kids): array {
            $firstDate = $notification->courseInstance->dates->first()?->starts_at;

            return [
                'instance' => $notification->courseInstance,
                'kids' => collect($notification->matching_kid_ids)
                    ->map(fn (int $id): ?Kid => $kids->get($id))
                    ->filter()
                    ->sortBy('name')
                    ->map(fn (Kid $kid): string => $kid->name.($kid->age($firstDate) !== null ? " ({$kid->age($firstDate)})" : ''))
                    ->values()
                    ->all(),
            ];
        });
    }

    /**
     * @return Collection<int, CourseInstanceNotification>
     */
    protected function notifications(): Collection
    {
        return CourseInstanceNotification::query()
            ->whereKey($this->notificationIds)
            ->with(['courseInstance.course', 'courseInstance.dates'])
            ->get()
            ->sortBy(fn (CourseInstanceNotification $notification) => $notification->courseInstance->dates->first()?->starts_at)
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    protected function kidNames(): Collection
    {
        return Kid::query()->whereIn('id', $this->notifications()->pluck('matching_kid_ids')->flatten()->unique())->orderBy('name')->pluck('name');
    }
}
