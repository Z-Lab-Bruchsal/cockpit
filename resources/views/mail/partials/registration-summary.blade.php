@foreach ($registrations as $registration)
**{{ $registration->courseInstance->displayTitle() }}**{{ $registration->courseInstance->title ? ' ('.$registration->courseInstance->course->name.')' : '' }}

@foreach ($registration->courseInstance->dates as $date)
- {{ $date->formattedRange() }}
@endforeach

@foreach ($registration->enrollments->whereNotIn('status', [\App\Enums\EnrollmentStatus::Cancelled, \App\Enums\EnrollmentStatus::Expired]) as $enrollment)
{{ $enrollment->displayKidName() }}: *{{ $enrollment->status->getLabel() }}*{{ $enrollment->status === \App\Enums\EnrollmentStatus::Waitlisted ? ' – wir melden uns, sobald ein Platz frei wird. Du hast dann '.$registration->courseInstance->waitlistOfferHours().' Stunden Zeit, ihn anzunehmen.' : '' }}<br>
@endforeach

@isset($manageUrls[$registration->id])
[Anmeldung ansehen oder stornieren]({{ $manageUrls[$registration->id] }})
@endisset

@endforeach
