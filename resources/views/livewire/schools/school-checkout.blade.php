<div>
    @error('cart')
        <p class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</p>
    @enderror

    @if ($this->registrations->isEmpty())
        <div class="rounded-2xl bg-gray-50 px-6 py-12 text-center">
            <p class="text-gray-600">Ihr Warenkorb ist leer.</p>
            <a href="{{ route('homepage.schools.slots') }}" class="mt-4 inline-block rounded-lg bg-brand px-4 py-2 font-semibold text-brand-contrast hover:opacity-90">Freie Termine ansehen</a>
        </div>
    @else
        @php($expiresAt = $this->registrations->min('expires_at'))

        <div
            x-data="{ remaining: Math.max(0, Math.floor(({{ $expiresAt->getTimestamp() }} * 1000 - Date.now()) / 1000)) }"
            x-init="setInterval(() => { remaining = Math.max(0, remaining - 1); if (remaining === 0) $wire.$refresh() }, 1000)"
            class="mb-6 flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
            role="status"
        >
            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            <span>Die Termine sind noch <strong x-text="Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0')"></strong> Minuten für Sie reserviert.</span>
        </div>

        <form wire:submit="checkout" class="flex flex-col gap-6">
            @foreach ($this->registrations as $registration)
                @php($instance = $registration->courseInstance)
                @php($venues = $instance->allowedVenues())
                <article wire:key="registration-{{ $registration->id }}" class="rounded-2xl border border-gray-200 bg-white p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ $instance->displayTitle() }}</h2>
                            <p class="text-sm text-gray-600">
                                {{ $instance->dates->first()?->formattedRange() }}
                                @if ($instance->dates->count() > 1)
                                    und {{ $instance->dates->count() - 1 }} weitere Termine
                                @endif
                            </p>
                            @if ($registration->replacesRegistration)
                                <p class="mt-1 inline-block rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-800">Verschiebung</p>
                                <p class="mt-1 text-xs text-gray-500">Ersetzt Ihren Termin am {{ $registration->replacesRegistration->courseInstance->dates->first()?->formattedRange() }}. Dieser bleibt bestehen, bis der neue Termin bestätigt ist.</p>
                            @endif
                        </div>
                        <button type="button" wire:click="remove({{ $registration->id }})" class="self-start text-sm text-gray-500 hover:text-red-600 hover:underline">Entfernen</button>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @if (count($venues) > 1)
                            <fieldset class="flex flex-col gap-2 text-sm sm:col-span-2">
                                <legend class="mb-1 font-medium text-gray-700">Wo soll der Kurs stattfinden?</legend>
                                @foreach ($venues as $venue)
                                    <label class="flex items-center gap-2 text-gray-700">
                                        <input type="radio" wire:model="classes.{{ $registration->id }}.venue" value="{{ $venue->value }}" class="border-gray-300 text-brand">
                                        {{ $venue->getLabel() }}@if ($venue === \App\Enums\SchoolVenue::School && $contact?->schoolAccount()) <span class="text-gray-500">({{ $contact->schoolAccount()->addressLine() }})</span>@endif
                                    </label>
                                @endforeach
                                @error("classes.{$registration->id}.venue")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
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
                            Klasse
                            <input type="text" wire:model="classes.{{ $registration->id }}.class_name" placeholder="z. B. 4b" class="rounded-lg border-gray-300">
                            @error("classes.{$registration->id}.class_name")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                            Anzahl Schüler*innen
                            <input type="number" min="1" max="200" wire:model="classes.{{ $registration->id }}.students" class="rounded-lg border-gray-300">
                            @error("classes.{$registration->id}.students")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                            Lehrkraft
                            <input type="text" wire:model="classes.{{ $registration->id }}.teacher_name" autocomplete="name" class="rounded-lg border-gray-300">
                            @error("classes.{$registration->id}.teacher_name")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                            Telefon für Rückfragen <span class="font-normal text-gray-500">(optional)</span>
                            <input type="tel" wire:model="classes.{{ $registration->id }}.teacher_phone" autocomplete="tel" class="rounded-lg border-gray-300">
                            @error("classes.{$registration->id}.teacher_phone")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="flex flex-col gap-1 text-sm font-medium text-gray-700 sm:col-span-2">
                            Thema / Anmerkung <span class="font-normal text-gray-500">(optional, z. B. Vorwissen, Schwerpunkt, Besonderheiten)</span>
                            <textarea wire:model="classes.{{ $registration->id }}.topic" rows="2" class="rounded-lg border-gray-300"></textarea>
                            @error("classes.{$registration->id}.topic")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                        </label>
                    </div>
                </article>
            @endforeach

            <a href="{{ route('homepage.schools.slots') }}" class="self-start text-sm font-semibold text-brand hover:underline">+ Weiteren Termin hinzufügen</a>

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

            <button type="submit" class="rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Verbindlich anfragen</button>
            <p class="text-xs text-gray-500">Die Anfrage ist eine Reservierung. Verbindlich wird der Termin erst, wenn die Kursleitung ihn bestätigt.</p>
        </form>
    @endif
</div>
