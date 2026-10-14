<x-homepage.layout title="Termin verschieben">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Termin verschieben</h1>
        <p class="mt-3 text-gray-600">
            Bisher gebucht: <strong>{{ $registration->courseInstance->displayTitle() }}</strong> am {{ $registration->courseInstance->dates->first()?->formattedRange() }}.
            Wählen Sie einen neuen Termin. Ihr bisheriger Termin bleibt bestehen, bis die Kursleitung den neuen bestätigt hat.
        </p>

        @if (session('school_error'))
            <p class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('school_error') }}</p>
        @endif

        <ul class="mt-6 flex flex-col gap-2">
            @forelse ($options as $instance)
                <li class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-medium text-gray-900">{{ $instance->dates->first()?->formattedRange() }}</p>
                        @if ($instance->title)<p class="text-sm text-gray-600">{{ $instance->title }}</p>@endif
                    </div>
                    <form method="POST" action="{{ route('homepage.schools.reschedule.hold', [$registration, $instance]) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-brand-contrast hover:opacity-90">Diesen Termin wählen</button>
                    </form>
                </li>
            @empty
                <li class="rounded-2xl bg-gray-50 px-6 py-8 text-center text-gray-600">Für diesen Kurs gibt es gerade keine anderen freien Termine. Bitte sprechen Sie uns an.</li>
            @endforelse
        </ul>

        <a href="{{ route('homepage.schools.account') }}" class="mt-6 inline-block text-sm font-semibold text-brand hover:underline">Zurück zum Schulkonto</a>
    </div>
</x-homepage.layout>
