@php
    $instance = $registration->courseInstance;
    $class = $registration->schoolClass();
    $isConfirmed = $registration->status === \App\Enums\RegistrationStatus::Confirmed;
@endphp

<x-homepage.layout title="Ihr Termin">
    <div class="mx-auto max-w-xl px-4 py-12 sm:py-16">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $instance->displayTitle() }}</h1>

        @if (session('status'))
            <p class="mt-6 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        @if (session('school_error'))
            <p class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('school_error') }}</p>
        @endif

        <dl class="mt-6 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-2xl border border-gray-200 bg-white p-5 text-sm">
            <dt class="font-medium text-gray-500">Status</dt>
            <dd>{{ $registration->status->getLabel() }}</dd>
            @foreach ($instance->dates as $date)
                <dt class="font-medium text-gray-500">{{ $loop->count > 1 ? 'Termin '.$loop->iteration : 'Termin' }}</dt>
                <dd>{{ $date->formattedRange() }}</dd>
            @endforeach
            <dt class="font-medium text-gray-500">Ort</dt>
            <dd>{{ $registration->venue?->getLabel() }}@if (filled($registration->visitAddress())): {{ $registration->visitAddress() }}@endif</dd>
            <dt class="font-medium text-gray-500">Schule</dt>
            <dd>{{ $registration->school?->name }}</dd>
            <dt class="font-medium text-gray-500">Klasse</dt>
            <dd>{{ $class['class_name'] }}@if ($registration->schoolClassLevel()) ({{ $registration->schoolClassLevel() }})@endif</dd>
            <dt class="font-medium text-gray-500">Lehrkraft</dt>
            <dd>{{ $class['teacher_name'] }}</dd>
            @if ($class['topic'])
                <dt class="font-medium text-gray-500">Thema / Anmerkung</dt>
                <dd>{{ $class['topic'] }}</dd>
            @endif
        </dl>

        @if ($isConfirmed && $instance->dates->first()?->starts_at->isFuture())
            <section class="mt-8 rounded-2xl border border-gray-200 bg-white p-5" aria-labelledby="bestaetigen">
                <h2 id="bestaetigen" class="text-lg font-semibold text-gray-900">Teilnahme bestätigen</h2>

                @if ($registration->teacher_confirmed_at)
                    <p class="mt-1 text-sm text-green-700">Bestätigt am {{ $registration->teacher_confirmed_at->format('d.m.Y H:i') }}. Sie können die Angaben trotzdem noch einmal anpassen.</p>
                @else
                    <p class="mt-1 text-sm text-gray-600">Bitte prüfen Sie die Schülerzahl und bestätigen Sie, dass der Termin klappt.</p>
                @endif

                <form method="POST" action="{{ $confirmUrl }}" class="mt-4 flex flex-col gap-4">
                    @csrf
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Anzahl Kinder
                        <input type="number" name="students" min="1" max="{{ $registration->courseInstance->maxClassSize() ?? 200 }}" value="{{ old('students', $class['students']) }}" required class="rounded-lg border-gray-300">
                        @error('students')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                        Nachricht an uns <span class="font-normal text-gray-500">(optional, z. B. Änderungen am Thema)</span>
                        <textarea name="remark" rows="3" class="rounded-lg border-gray-300">{{ old('remark') }}</textarea>
                        @error('remark')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <button type="submit" class="rounded-lg bg-green-600 px-4 py-3 font-semibold text-white hover:bg-green-700">Termin klappt – bestätigen</button>
                </form>
            </section>
        @endif

        @if ($canChange)
            <section class="mt-8 rounded-2xl bg-gray-50 p-5" aria-labelledby="aendern">
                <h2 id="aendern" class="text-lg font-semibold text-gray-900">Etwas ist dazwischengekommen?</h2>
                <p class="mt-1 text-sm text-gray-600">Bis zum {{ $instance->rescheduleDeadline()->format('d.m.Y') }} können Sie den Termin selbst stornieren oder auf einen anderen Termin verschieben.</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('homepage.schools.reschedule', $registration) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-gray-100">Verschieben</a>
                    <form method="POST" action="{{ $cancelUrl }}" x-data @submit="if (! confirm('Den Termin wirklich stornieren?')) $event.preventDefault()">
                        @csrf
                        <button type="submit" class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Stornieren</button>
                    </form>
                </div>
                <p class="mt-2 text-xs text-gray-500">Zum Verschieben melden Sie sich mit Ihrem Konto an.</p>
            </section>
        @elseif ($registration->status->isActive())
            <p class="mt-8 text-sm text-gray-600">Änderungen sind jetzt nur noch telefonisch möglich{{ \App\Models\CompanySetting::current()->contact_phone ? ': '.\App\Models\CompanySetting::current()->contact_phone : '' }}.</p>
        @endif
    </div>
</x-homepage.layout>
