@props(['instance', 'availability'])

@php
    $cart = app(\App\Services\Registrations\CartManager::class)->current();
    $isInCart = $cart?->heldRegistrations()->where('course_instance_id', $instance->id)->exists() ?? false;
    $freeSeats = $instance->freeSeats();
    $maxSeats = \App\Services\Registrations\CourseReservationService::MAX_SEATS_PER_REGISTRATION;
    $errorBag = $errors->getBag('instance'.$instance->id);
@endphp

@if ($isInCart)
    <a href="{{ route('homepage.cart') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand/10 px-4 py-2 font-semibold text-gray-900 hover:bg-brand/20">
        In deinem Warenkorb – zur Anmeldung
    </a>
@elseif ($availability === \App\Enums\CourseAvailability::Reserved)
    <p>Gerade sind alle Plätze in Reservierung. Schau in ein paar Minuten noch einmal vorbei – nicht abgeschlossene Anmeldungen werden wieder frei.</p>
@elseif ($availability->acceptsRegistrations() && $maxSeats > 0)
    <form method="POST" action="{{ route('homepage.cart.add', $instance) }}" class="flex flex-wrap items-end gap-3">
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
            {{ $availability === \App\Enums\CourseAvailability::Waitlist ? 'Auf die Warteliste' : 'Anmeldung beginnen' }}
        </button>
    </form>
    @if ($availability === \App\Enums\CourseAvailability::Waitlist)
        <p class="mt-2 text-sm text-gray-600">Wird ein Platz frei, bekommst du eine E-Mail und hast dann {{ $instance->waitlistOfferHours() }} Stunden Zeit, ihn anzunehmen.</p>
    @elseif ($freeSeats < $maxSeats)
        <p class="mt-2 text-sm text-gray-600">Noch {{ $freeSeats }} {{ $freeSeats === 1 ? 'Platz' : 'Plätze' }} frei. Meldest du mehr Geschwister an, prüfen wir, ob alle teilnehmen können.</p>
    @endif
    @if ($errorBag->has('seats'))
        <p class="mt-2 text-sm text-red-600" role="alert">{{ $errorBag->first('seats') }}</p>
    @endif
@endif
