<div class="flex flex-col gap-10">
    {{-- Registrations --}}
    <section aria-labelledby="anmeldungen">
        <h2 id="anmeldungen" class="mb-4 text-2xl font-bold tracking-tight text-gray-900">Meine Anmeldungen</h2>

        @if ($registrations->isEmpty())
            <p class="rounded-2xl bg-gray-50 px-6 py-8 text-center text-gray-500">
                Keine anstehenden Kurse. <a href="{{ route('homepage.courses.index') }}" class="font-semibold text-brand hover:underline">Jetzt Kurse entdecken</a>
            </p>
        @else
            <ul class="flex flex-col gap-4">
                @foreach ($registrations as $registration)
                    <li wire:key="registration-{{ $registration->id }}" class="rounded-2xl border border-gray-200 bg-white p-5">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <a href="{{ $registration->courseInstance->course->url() }}" class="text-lg font-semibold text-gray-900 hover:underline">{{ $registration->courseInstance->displayTitle() }}</a>
                                <p class="text-sm text-gray-600">{{ $registration->courseInstance->dates->first()?->formattedRange() }}@if ($registration->courseInstance->dates->count() > 1) und {{ $registration->courseInstance->dates->count() - 1 }} weitere Termine @endif</p>
                            </div>
                            @if ($registration->status === \App\Enums\RegistrationStatus::PendingConfirmation)
                                <span class="self-start rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Bitte per E-Mail bestätigen</span>
                            @endif
                        </div>
                        <ul class="mt-3 divide-y divide-gray-100 border-t border-gray-100 text-sm">
                            @foreach ($registration->activeEnrollments as $enrollment)
                                <li class="flex items-center justify-between gap-3 py-2">
                                    <span><span class="font-medium">{{ $enrollment->displayKidName() }}</span> <span class="text-gray-500">– {{ $enrollment->status->getLabel() }}</span></span>
                                    @if (in_array($registration->status, [\App\Enums\RegistrationStatus::Confirmed, \App\Enums\RegistrationStatus::AwaitingApproval], true))
                                        <form method="POST" action="{{ route('homepage.account.registration.cancel', $registration) }}" onsubmit="return confirm('{{ $enrollment->displayKidName() }} wirklich abmelden?')">
                                            @csrf
                                            <input type="hidden" name="enrollment" value="{{ $enrollment->id }}">
                                            <button type="submit" class="text-gray-500 hover:text-red-600 hover:underline">Abmelden</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Children --}}
    <section aria-labelledby="kinder">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 id="kinder" class="text-2xl font-bold tracking-tight text-gray-900">Meine Kinder</h2>
            @unless ($isEditingKid)
                <button type="button" wire:click="createKid" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-brand-contrast hover:opacity-90">Kind hinzufügen</button>
            @endunless
        </div>

        @if ($isEditingKid)
            <form wire:submit="saveKid" class="mb-6 flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5">
                <h3 class="font-semibold text-gray-900">{{ $editingKidId ? 'Kind bearbeiten' : 'Neues Kind' }}</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Vorname und Name
                        <input type="text" wire:model="kidName" class="rounded-lg border-gray-300">
                        @error('kidName')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Geburtsdatum
                        <input type="date" wire:model="kidBirthdate" max="{{ now()->subDay()->toDateString() }}" class="rounded-lg border-gray-300">
                        @if ($kidBirthdateIsEstimated)
                            <span class="text-xs font-normal text-amber-700">Wir kennen bisher nur das ungefähre Alter – bitte trage das genaue Geburtsdatum ein.</span>
                        @endif
                        @error('kidBirthdate')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                </div>

                @if ($this->interests->isNotEmpty())
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">Wofür interessiert sich dein Kind?</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($this->interests as $interest)
                                <label wire:key="interest-{{ $interest->id }}" class="flex cursor-pointer items-center gap-2 rounded-full border border-gray-300 bg-white px-3 py-1.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand/10">
                                    <input type="checkbox" wire:model="kidInterestIds" value="{{ $interest->id }}" class="rounded border-gray-300 text-brand">
                                    {{ $interest->name }}
                                </label>
                            @endforeach
                        </div>
                        @error('kidInterestIds.*')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </fieldset>
                @endif

                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-brand-contrast hover:opacity-90">Speichern</button>
                    <button type="button" wire:click="cancelKid" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Abbrechen</button>
                </div>
            </form>
        @endif

        @if ($this->kids->isEmpty())
            <p class="rounded-2xl bg-gray-50 px-6 py-8 text-center text-gray-500">Du hast noch keine Kinder hinterlegt.</p>
        @else
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ($this->kids as $kid)
                    <li wire:key="kid-{{ $kid->id }}" class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5">
                        <div>
                            <p class="text-lg font-semibold text-gray-900">{{ $kid->name }}</p>
                            @if ($kid->age() !== null)
                                <p class="text-sm text-gray-600">{{ $kid->age() }} Jahre</p>
                            @endif
                        </div>
                        @if ($kid->interests->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($kid->interests as $interest)
                                    <x-homepage.interest-badge :interest="$interest" />
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-auto flex gap-3 text-sm">
                            <button type="button" wire:click="editKid({{ $kid->id }})" class="font-semibold text-brand hover:underline">Bearbeiten</button>
                            <button type="button" wire:click="removeKid({{ $kid->id }})" wire:confirm="{{ $kid->name }} wirklich aus deinem Konto entfernen?" class="text-gray-500 hover:text-red-600 hover:underline">Entfernen</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Contact details --}}
    <section aria-labelledby="daten">
        <h2 id="daten" class="mb-4 text-2xl font-bold tracking-tight text-gray-900">Meine Daten</h2>

        <form wire:submit="saveProfile" class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Name
                    <input type="text" wire:model="name" autocomplete="name" class="rounded-lg border-gray-300">
                    @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <div class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    E-Mail-Adresse
                    <p class="rounded-lg bg-gray-50 px-3 py-2 font-normal text-gray-600">{{ $contact->email }}</p>
                </div>
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Telefon <span class="font-normal text-gray-500">(für Rückfragen, optional)</span>
                    <input type="tel" wire:model="phone" autocomplete="tel" class="rounded-lg border-gray-300">
                    @error('phone')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Straße <span class="font-normal text-gray-500">(optional)</span>
                    <input type="text" wire:model="street" autocomplete="street-address" class="rounded-lg border-gray-300">
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    PLZ
                    <input type="text" wire:model="zip" autocomplete="postal-code" class="rounded-lg border-gray-300">
                    @error('zip')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Ort
                    <input type="text" wire:model="city" autocomplete="address-level2" class="rounded-lg border-gray-300">
                </label>
            </div>

            <label class="flex items-start gap-3 rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                <input type="checkbox" wire:model="wantsInterestMails" class="mt-0.5 rounded border-gray-300 text-brand">
                <span>Ja, informiert mich per E-Mail über neue Kurse, die zu den Interessen und dem Alter meiner Kinder passen. Die Einwilligung kann ich jederzeit hier oder über den Link in jeder E-Mail widerrufen.</span>
            </label>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-brand-contrast hover:opacity-90">Speichern</button>
                @if (session('profile_saved'))
                    <span class="text-sm text-green-700" role="status">Gespeichert</span>
                @endif
            </div>
        </form>

    </section>

    {{-- Email address --}}
    <section aria-labelledby="email">
        <h2 id="email" class="mb-4 text-2xl font-bold tracking-tight text-gray-900">E-Mail-Adresse ändern</h2>

        <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5">
            @if ($pendingEmailChange)
                <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900" role="status">
                    @if ($pendingEmailChange->isWaitingForOldAddress())
                        Wir haben dir an <strong>{{ $pendingEmailChange->old_email }}</strong> einen Link geschickt. Bitte bestätige dort zuerst die Änderung auf <strong>{{ $pendingEmailChange->new_email }}</strong>.
                    @else
                        Fast fertig: Bitte bestätige jetzt noch den Link, den wir an <strong>{{ $pendingEmailChange->new_email }}</strong> geschickt haben.
                    @endif
                    <button type="button" wire:click="cancelEmailChange" class="ml-1 font-semibold underline">Abbrechen</button>
                </div>
            @endif

            <form wire:submit="requestEmailChange" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label class="flex grow flex-col gap-1 text-sm font-medium text-gray-700">
                    Neue E-Mail-Adresse
                    <input type="email" wire:model="newEmail" autocomplete="email" class="rounded-lg border-gray-300">
                    @error('newEmail')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-brand-contrast hover:opacity-90">Änderung anfordern</button>
            </form>

            <p class="text-xs text-gray-500">Zur Sicherheit bestätigst du die Änderung zuerst über deine bisherige Adresse und danach über die neue.</p>

            <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                <strong>Kein Zugriff mehr auf {{ $contact->email }}?</strong>
                Dann kannst du die Änderung nicht selbst bestätigen.
                <a href="{{ route('homepage.contact-form', ['anliegen' => \App\Enums\ContactFormTopic::LostEmailAccess->value]) }}" class="font-semibold text-brand hover:underline">Schreib uns über das Kontaktformular</a>
                – wir helfen dir weiter.
            </p>
        </div>

        <p class="mt-3 text-xs text-gray-500">Du möchtest dein Konto löschen? <a href="{{ route('homepage.contact-form', ['anliegen' => \App\Enums\ContactFormTopic::Privacy->value]) }}" class="underline hover:text-gray-700">Schreib uns kurz</a>, wir kümmern uns darum.</p>
    </section>
</div>
