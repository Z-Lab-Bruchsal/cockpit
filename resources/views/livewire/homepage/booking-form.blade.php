<div>
    @error('booking')
        <p class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</p>
    @enderror

    @if ($this->registration === null)
        <div class="rounded-2xl bg-white px-6 py-12 text-center">
            <p class="text-gray-600">Die Zeit für diese Buchung ist abgelaufen, die Plätze wurden wieder freigegeben.</p>
            <a href="{{ route('homepage.booking.show', $token) }}" class="mt-4 inline-block rounded-lg bg-brand px-4 py-2 font-semibold text-brand-contrast hover:opacity-90">Neu starten</a>
        </div>
    @else
        @php($registration = $this->registration)

        <form wire:submit="checkout" class="flex flex-col gap-8">
            {{-- Course and children --}}
            <section class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5" aria-label="Kurs">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $registration->courseInstance->displayTitle() }}</h2>
                    <p class="text-sm text-gray-600">
                        {{ $registration->courseInstance->dates->first()?->formattedRange() }}
                        @if ($registration->courseInstance->dates->count() > 1)
                            und {{ $registration->courseInstance->dates->count() - 1 }} weitere Termine
                        @endif
                    </p>
                    @if ($registration->isOnWaitlist())
                        <p class="mt-1 inline-block rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-800">Warteliste</p>
                        <p class="mt-1 text-xs text-gray-500">Wird ein Platz frei, hast du {{ $registration->courseInstance->waitlistOfferHours() }} Stunden Zeit, ihn anzunehmen.</p>
                    @elseif ($registration->needs_approval)
                        <p class="mt-1 inline-block rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Freigabe nötig</p>
                        <p class="mt-1 text-xs text-gray-500">Für alle Geschwister sind nicht mehr genug Plätze frei. Nach deiner Anmeldung prüfen wir, ob trotzdem alle teilnehmen können.</p>
                    @endif
                </div>

                @foreach ($kids as $index => $kid)
                    <fieldset wire:key="kid-{{ $index }}" class="grid gap-3 rounded-xl bg-white p-4 sm:grid-cols-2">
                        <legend class="sr-only">Kind {{ $index + 1 }}</legend>
                        <p class="text-sm font-semibold text-gray-700 sm:col-span-2">Kind {{ $index + 1 }}</p>

                        @if ($this->ownKids->isNotEmpty())
                            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700 sm:col-span-2">
                                Aus meinem Konto
                                <select wire:model.live="kids.{{ $index }}.kid_id" class="rounded-lg border-gray-300">
                                    <option value="">Anderes Kind eingeben …</option>
                                    @foreach ($this->ownKids as $ownKid)
                                        <option value="{{ $ownKid->id }}">{{ $ownKid->name }}@if ($ownKid->age() !== null) ({{ $ownKid->age() }} Jahre)@endif</option>
                                    @endforeach
                                </select>
                                @error("kids.{$index}.kid_id")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                            </label>
                        @endif

                        @php($selectedOwnKid = filled($kid['kid_id']) ? $this->ownKids->firstWhere('id', (int) $kid['kid_id']) : null)

                        @if ($selectedOwnKid && ! $selectedOwnKid->exactBirthdate())
                            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700 sm:col-span-2">
                                Geburtsdatum von {{ $selectedOwnKid->name }}
                                <input type="date" wire:model="kids.{{ $index }}.birthdate" max="{{ now()->subDay()->toDateString() }}" class="rounded-lg border-gray-300">
                                <span class="font-normal text-gray-500">Für die Anmeldung brauchen wir das genaue Geburtsdatum. Wir speichern es auch in deinem Konto.</span>
                                @error("kids.{$index}.birthdate")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                            </label>
                        @endif

                        @if (blank($kid['kid_id']))
                            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                                Name des Kindes
                                <input type="text" wire:model="kids.{{ $index }}.name" class="rounded-lg border-gray-300">
                                @error("kids.{$index}.name")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                            </label>
                            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                                Geburtsdatum
                                <input type="date" wire:model="kids.{{ $index }}.birthdate" max="{{ now()->subDay()->toDateString() }}" class="rounded-lg border-gray-300">
                                @error("kids.{$index}.birthdate")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                            </label>
                        @endif
                    </fieldset>
                @endforeach

                <x-homepage.booking-limit-hint class="text-xs text-gray-500" />
            </section>

            {{-- Contact --}}
            <section class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5" aria-labelledby="kontaktdaten">
                <h2 id="kontaktdaten" class="text-lg font-semibold text-gray-900">Deine Kontaktdaten</h2>

                @guest('contact')
                    <p class="text-sm text-gray-600">Du hast schon ein Konto? <a href="{{ route('homepage.login', ['weiter' => route('homepage.booking.show', $token, absolute: false)]) }}" class="font-semibold text-brand-ink hover:underline">Melde dich an</a>, dann sind deine Daten und Kinder schon ausgefüllt. Ein Konto ist aber nicht nötig.</p>
                @endguest

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Name (Erziehungsberechtigte*r)
                        <input type="text" wire:model="name" autocomplete="name" class="rounded-lg border-gray-300">
                        @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        E-Mail-Adresse
                        <input type="email" wire:model="email" autocomplete="email" class="rounded-lg border-gray-300">
                        @error('email')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Telefon für Rückfragen
                        <input type="tel" wire:model="phone" autocomplete="tel" required class="rounded-lg border-gray-300">
                        @error('phone')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700 sm:col-span-2">
                        Nachricht an uns <span class="font-normal text-gray-500">(optional, z. B. Allergien)</span>
                        <textarea wire:model="message" rows="2" class="rounded-lg border-gray-300"></textarea>
                        @error('message')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                </div>

                <label class="flex items-start gap-3 text-sm text-gray-700">
                    <input type="checkbox" wire:model="wantsInterestMails" class="mt-0.5 rounded border-gray-300 text-brand-ink">
                    <span>Informiert mich per E-Mail über neue Kurse, die zu meinen Kindern passen (jederzeit abbestellbar).</span>
                </label>

                <label class="flex items-start gap-3 text-sm text-gray-700">
                    <input type="checkbox" wire:model="acceptsPrivacy" class="mt-0.5 rounded border-gray-300 text-brand-ink">
                    <span>
                        Ich bin einverstanden, dass meine Angaben und die meiner Kinder zur Durchführung der Kurse gespeichert werden.
                        @if ($privacySection = \App\Models\Section::query()->where('slug', 'datenschutz')->where('is_visible', true)->first())
                            Mehr in der <a href="{{ $privacySection->url() }}" class="text-brand-ink underline" target="_blank">Datenschutzerklärung</a>.
                        @endif
                    </span>
                </label>
                @error('acceptsPrivacy')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </section>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600">
                    @auth('contact')
                        Mit deinem Konto ist die Anmeldung sofort verbindlich.
                    @else
                        Wir schicken dir eine E-Mail – erst mit deiner Bestätigung ist die Anmeldung verbindlich.
                    @endauth
                </p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
                    <button type="submit" form="cancel-booking" class="px-4 py-3 text-sm text-gray-500 hover:text-red-600 hover:underline">Abbrechen</button>
                    <button type="submit" class="rounded-lg bg-brand px-6 py-3 font-semibold text-brand-contrast hover:opacity-90" wire:loading.attr="disabled">Verbindlich anmelden</button>
                </div>
            </div>
        </form>

        <form id="cancel-booking" method="POST" action="{{ route('homepage.booking.cancel', $token) }}" class="hidden">
            @csrf
        </form>
    @endif
</div>
