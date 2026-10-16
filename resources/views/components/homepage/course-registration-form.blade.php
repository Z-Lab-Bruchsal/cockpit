@props(['instance', 'availability'])

@php
    $isSchoolCourse = $instance->course->isForSchoolClasses();
    $isSchoolContact = (bool) auth('contact')->user()?->canBookForSchool();
    $freeSeats = $instance->freeSeats();
    $maxSeats = \App\Models\CompanySetting::current()->max_seats_per_registration;
    $errorBag = $errors->getBag('instance'.$instance->id);
@endphp

@if ($isSchoolCourse)
    <p>Dieser Kurs ist für Schulklassen und wird über den <a href="{{ route('homepage.schools.slots') }}#termin-{{ $instance->id }}" class="font-semibold text-brand hover:underline">Bereich für Schulen</a> gebucht.</p>
@elseif ($isSchoolContact)
    <p>Mit einem Schulkonto können keine Kurse für einzelne Kinder gebucht werden. Bitte melden Sie sich dafür ab oder buchen Sie über den <a href="{{ route('homepage.schools.slots') }}" class="font-semibold text-brand hover:underline">Bereich für Schulen</a>.</p>
@elseif ($availability === \App\Enums\CourseAvailability::Reserved)
    <p>Gerade sind alle Plätze in Reservierung. Schau in ein paar Minuten noch einmal vorbei – nicht abgeschlossene Anmeldungen werden wieder frei.</p>
@elseif ($availability->acceptsRegistrations() && $maxSeats > 0)
    <form method="POST" action="{{ route('homepage.booking.start', $instance) }}" class="flex flex-wrap items-end gap-3">
        @csrf
        <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
            Anzahl Kinder
            <select name="seats" class="rounded-lg border-gray-300 text-sm">
                @for ($seats = 1; $seats <= $maxSeats; $seats++)
                    <option value="{{ $seats }}">{{ $seats }}</option>
                @endfor
            </select>
        </label>
        <button type="submit" class="rounded-lg bg-brand px-4 py-2 font-semibold text-brand-contrast hover:opacity-90">
            {{ $availability === \App\Enums\CourseAvailability::Waitlist ? 'Auf die Warteliste' : 'Buchen' }}
        </button>
    </form>
    <p class="mt-2 text-sm text-gray-600">Nach dem Klick sind die Plätze {{ \App\Models\CompanySetting::current()->hold_minutes }} Minuten für dich reserviert – Zeit genug, um die Anmeldung auszufüllen.</p>
    <x-homepage.booking-limit-hint class="mt-2" />
    @if ($availability === \App\Enums\CourseAvailability::Waitlist)
        <p class="mt-2 text-sm text-gray-600">Wird ein Platz frei, bekommst du eine E-Mail und hast dann {{ $instance->waitlistOfferHours() }} Stunden Zeit, ihn anzunehmen.</p>
    @elseif ($freeSeats < $maxSeats)
        <p class="mt-2 text-sm text-gray-600">Noch {{ $freeSeats }} {{ $freeSeats === 1 ? 'Platz' : 'Plätze' }} frei. Meldest du mehr Geschwister an, prüfen wir, ob alle teilnehmen können.</p>
    @endif
    @if ($errorBag->has('seats'))
        <p class="mt-2 text-sm text-red-600" role="alert">{{ $errorBag->first('seats') }}</p>
    @endif
@endif
