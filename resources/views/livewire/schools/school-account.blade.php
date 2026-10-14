<div class="flex flex-col gap-10">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">{{ $contact->isSchool() ? $contact->name : 'Mein Schulkonto' }}</h1>
            <p class="mt-1 text-gray-600">
                @if ($contact->isSchool())
                    Schulkonto · {{ $contact->email }}
                @else
                    Lehrkraft der {{ $contact->school?->name }} · {{ $contact->email }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('homepage.schools.slots') }}" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-brand-contrast hover:opacity-90">Termin buchen</a>
            <form method="POST" action="{{ route('homepage.logout') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold hover:bg-gray-100">Abmelden</button>
            </form>
        </div>
    </div>

    {{-- Bookings --}}
    <section aria-labelledby="buchungen">
        <h2 id="buchungen" class="text-xl font-semibold text-gray-900">{{ $contact->isSchool() ? 'Buchungen der Schule' : 'Meine Buchungen' }}</h2>

        <ul class="mt-4 flex flex-col gap-3">
            @forelse ($this->registrations as $registration)
                @php($instance = $registration->courseInstance)
                @php($class = $registration->schoolClass())
                <li wire:key="booking-{{ $registration->id }}" class="rounded-2xl border border-gray-200 bg-white p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $instance->displayTitle() }}</p>
                            <p class="text-sm text-gray-600">{{ $instance->dates->first()?->formattedRange() }} · {{ $registration->venue?->getLabel() }}</p>
                            <p class="text-sm text-gray-600">Klasse {{ $class['class_name'] }}, {{ $class['students'] }} Schüler*innen · {{ $class['teacher_name'] }}</p>
                            <p class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold {{ match ($registration->status) {
                                \App\Enums\RegistrationStatus::Confirmed => 'bg-green-50 text-green-800',
                                \App\Enums\RegistrationStatus::Requested => 'bg-amber-50 text-amber-800',
                                default => 'bg-gray-100 text-gray-700',
                            } }}">{{ $registration->status === \App\Enums\RegistrationStatus::Requested ? 'Angefragt – wartet auf Bestätigung' : $registration->status->getLabel() }}</p>
                            @if ($registration->replaces_registration_id && $registration->status === \App\Enums\RegistrationStatus::Requested)
                                <p class="mt-1 text-xs text-gray-500">Verschiebung eines bestehenden Termins</p>
                            @endif
                        </div>

                        @if ($schoolBookings->canBeChangedBySchool($registration))
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <a href="{{ route('homepage.schools.reschedule', $registration) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold hover:bg-gray-100">Verschieben</a>
                                <form method="POST" action="{{ route('homepage.schools.cancel', $registration) }}" x-data @submit="if (! confirm('Den Termin wirklich stornieren?')) $event.preventDefault()">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-red-300 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50">Stornieren</button>
                                </form>
                            </div>
                        @elseif ($registration->status->isActive())
                            <p class="text-xs text-gray-500 sm:max-w-48 sm:text-right">Änderungen nur noch telefonisch</p>
                        @endif
                    </div>
                </li>
            @empty
                <li class="rounded-2xl bg-gray-50 px-6 py-8 text-center text-gray-600">Noch keine Buchungen. <a href="{{ route('homepage.schools.slots') }}" class="font-semibold text-brand hover:underline">Freie Termine ansehen</a></li>
            @endforelse
        </ul>
    </section>

    {{-- Teachers --}}
    @if ($contact->isSchool())
        <section class="rounded-2xl border border-gray-200 bg-white p-5" aria-labelledby="lehrkraefte">
            <h2 id="lehrkraefte" class="text-xl font-semibold text-gray-900">Lehrkräfte</h2>
            <p class="mt-1 text-sm text-gray-600">Eingeladene Lehrkräfte melden sich mit ihrer eigenen Adresse an und buchen für ihre Klassen. Bestätigungen gehen in Kopie an das Schulkonto.</p>

            @if (session('teacher_invited'))
                <p class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800" role="status">Die Einladung an {{ session('teacher_invited') }} ist verschickt.</p>
            @endif

            <ul class="mt-4 divide-y divide-gray-100">
                @forelse ($this->teachers as $teacher)
                    <li wire:key="teacher-{{ $teacher->id }}" class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span>
                            <span class="font-medium text-gray-900">{{ $teacher->name }}</span>
                            <span class="text-gray-500">{{ $teacher->email }}</span>
                            @unless ($teacher->hasVerifiedEmail())
                                <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800">eingeladen</span>
                            @endunless
                        </span>
                        <button type="button" wire:click="removeTeacher({{ $teacher->id }})" wire:confirm="Zugang von {{ $teacher->name }} entfernen? Die Buchungen bleiben bei der Schule." class="text-gray-500 hover:text-red-600 hover:underline">Entfernen</button>
                    </li>
                @empty
                    <li class="py-2 text-sm text-gray-500">Noch keine Lehrkräfte eingeladen.</li>
                @endforelse
            </ul>

            <form wire:submit="inviteTeacher" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Name
                    <input type="text" wire:model="teacherName" class="rounded-lg border-gray-300">
                    @error('teacherName')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Dienstliche E-Mail-Adresse
                    <input type="email" wire:model="teacherEmail" class="rounded-lg border-gray-300">
                    @error('teacherEmail')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <button type="submit" class="rounded-lg bg-brand px-4 py-2 font-semibold text-brand-contrast hover:opacity-90">Einladen</button>
            </form>
        </section>
    @endif

    {{-- Profile --}}
    <section class="rounded-2xl border border-gray-200 bg-white p-5" aria-labelledby="daten">
        <h2 id="daten" class="text-xl font-semibold text-gray-900">{{ $contact->isSchool() ? 'Daten der Schule' : 'Meine Daten' }}</h2>

        @if (session('profile_saved'))
            <p class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800" role="status">Gespeichert.</p>
        @endif

        <form wire:submit="saveProfile" class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700 sm:col-span-2">
                {{ $contact->isSchool() ? 'Name der Schule' : 'Name' }}
                <input type="text" wire:model="name" class="rounded-lg border-gray-300">
                @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            @if ($contact->isSchool())
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700 sm:col-span-2">
                    Straße und Hausnummer
                    <input type="text" wire:model="street" class="rounded-lg border-gray-300">
                    @error('street')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    PLZ
                    <input type="text" wire:model="zip" class="rounded-lg border-gray-300">
                    @error('zip')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Ort
                    <input type="text" wire:model="city" class="rounded-lg border-gray-300">
                    @error('city')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Ansprechpartner*in
                    <input type="text" wire:model="contactPerson" class="rounded-lg border-gray-300">
                    @error('contactPerson')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
            @endif
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Telefon
                <input type="tel" wire:model="phone" class="rounded-lg border-gray-300">
                @error('phone')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <div class="sm:col-span-2">
                <button type="submit" class="rounded-lg bg-brand px-4 py-2 font-semibold text-brand-contrast hover:opacity-90">Speichern</button>
            </div>
        </form>
        <p class="mt-4 text-xs text-gray-500">Die E-Mail-Adresse kann nur das Z-LAB-Team ändern. <a href="{{ route('homepage.contact-form') }}" class="text-brand hover:underline">Schreiben Sie uns</a>.</p>
    </section>
</div>
