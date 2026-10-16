<div>
    @error('booking')
        <p class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</p>
    @enderror

    @if ($this->registration === null)
        <div class="rounded-2xl bg-gray-50 px-6 py-12 text-center">
            <p class="text-gray-600">Die Zeit für diese Buchung ist abgelaufen, der Termin wurde wieder freigegeben.</p>
            <a href="{{ route('homepage.schools.booking.show', $token) }}" class="mt-4 inline-block rounded-lg bg-brand px-4 py-2 font-semibold text-brand-contrast hover:opacity-90">Neu starten</a>
        </div>
    @else
        @php($registration = $this->registration)
        @php($instance = $registration->courseInstance)
        @php($course = $instance->course)
        @php($venues = $instance->allowedVenues())
        @php($maxClassSize = $instance->maxClassSize())

        <form wire:submit="checkout" class="flex flex-col gap-6">
            <article class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="text-lg font-semibold text-gray-900">{{ $instance->displayTitle() }}</h2>
                <p class="text-sm text-gray-600">
                    {{ $instance->dates->first()?->formattedRange() }}
                    @if ($instance->dates->count() > 1)
                        und {{ $instance->dates->count() - 1 }} weitere Termine
                    @endif
                </p>
                @if ($course->audienceLabel() || $maxClassSize)
                    <p class="mt-1 text-sm text-gray-600">
                        {{ collect([$course->audienceLabel(), $maxClassSize ? "max. {$maxClassSize} Kinder" : null])->filter()->implode(' · ') }}
                    </p>
                @endif
                @if ($registration->replacesRegistration)
                    <p class="mt-1 inline-block rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-800">Verschiebung</p>
                    <p class="mt-1 text-xs text-gray-500">Ersetzt Ihren Termin am {{ $registration->replacesRegistration->courseInstance->dates->first()?->formattedRange() }}. Dieser bleibt bestehen, bis der neue Termin bestätigt ist.</p>
                @endif

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @if (count($venues) > 1)
                        <fieldset class="flex flex-col gap-2 text-sm sm:col-span-2">
                            <legend class="mb-1 font-medium text-gray-700">Wo soll der Kurs stattfinden?</legend>
                            @foreach ($venues as $venueOption)
                                <label class="flex items-center gap-2 text-gray-700">
                                    <input type="radio" wire:model="venue" value="{{ $venueOption->value }}" class="border-gray-300 text-brand">
                                    {{ $venueOption->getLabel() }}@if ($venueOption === \App\Enums\SchoolVenue::School && $contact?->schoolAccount()) <span class="text-gray-500">({{ $contact->schoolAccount()->addressLine() }})</span>@endif
                                </label>
                            @endforeach
                            @error('venue')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                        </fieldset>
                    @else
                        <p class="text-sm text-gray-600 sm:col-span-2">
                            Ort: <strong>{{ $venues[0]->getLabel() }}</strong>
                            @if ($venues[0] === \App\Enums\SchoolVenue::School && $contact?->schoolAccount())
                                – wir kommen zu Ihnen: {{ $contact->schoolAccount()->addressLine() }}
                            @endif
                        </p>
                    @endif

                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Schulart
                        <select wire:model="schoolType" class="rounded-lg border-gray-300">
                            <option value="">Bitte wählen …</option>
                            @foreach ($course->allowedSchoolTypes() as $type)
                                <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                            @endforeach
                        </select>
                        @error('schoolType')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Klassenstufe
                        <select wire:model="grade" class="rounded-lg border-gray-300">
                            <option value="">Bitte wählen …</option>
                            @foreach ($course->allowedGrades() as $gradeOption)
                                <option value="{{ $gradeOption }}">{{ $gradeOption }}. Klasse</option>
                            @endforeach
                        </select>
                        @error('grade')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Klasse
                        <input type="text" wire:model="className" placeholder="z. B. 4b" class="rounded-lg border-gray-300">
                        @error('className')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Anzahl Kinder
                        <input type="number" min="1" max="{{ $maxClassSize ?? 200 }}" wire:model="students" class="rounded-lg border-gray-300">
                        @if ($maxClassSize)
                            <span class="font-normal text-gray-500">Höchstens {{ $maxClassSize }} Kinder</span>
                        @endif
                        @error('students')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Lehrkraft
                        <input type="text" wire:model="teacherName" autocomplete="name" class="rounded-lg border-gray-300">
                        @error('teacherName')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Telefon für Rückfragen
                        <input type="tel" wire:model="teacherPhone" autocomplete="tel" required class="rounded-lg border-gray-300">
                        @error('teacherPhone')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700 sm:col-span-2">
                        Thema / Anmerkung <span class="font-normal text-gray-500">(optional, z. B. Vorwissen, Schwerpunkt, Besonderheiten)</span>
                        <textarea wire:model="topic" rows="2" class="rounded-lg border-gray-300"></textarea>
                        @error('topic')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                </div>
            </article>

            <section class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5">
                <p class="text-sm text-gray-600">
                    Gebucht wird für die <strong>{{ $contact?->schoolAccount()?->name }}</strong>. Die Bestätigung geht an {{ $contact?->email }}{{ $contact?->isTeacher() ? ' und in Kopie an die Schule' : '' }}.
                </p>
                <label class="flex items-start gap-3 text-sm text-gray-700">
                    <input type="checkbox" wire:model="acceptsPrivacy" class="mt-0.5 rounded border-gray-300 text-brand">
                    <span>Ich bin einverstanden, dass die Angaben zur Durchführung der Kurse gespeichert werden.</span>
                </label>
                @error('acceptsPrivacy')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </section>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500">Die Anfrage ist eine Reservierung. Verbindlich wird der Termin erst, wenn die Kursleitung ihn bestätigt.</p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
                    <button type="submit" form="cancel-booking" class="px-4 py-3 text-sm text-gray-500 hover:text-red-600 hover:underline">Abbrechen</button>
                    <button type="submit" class="rounded-lg bg-brand px-6 py-3 font-semibold text-brand-contrast hover:opacity-90" wire:loading.attr="disabled">Verbindlich anfragen</button>
                </div>
            </div>
        </form>

        <form id="cancel-booking" method="POST" action="{{ route('homepage.schools.booking.cancel', $token) }}" class="hidden">
            @csrf
        </form>
    @endif
</div>
