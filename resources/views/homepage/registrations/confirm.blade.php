<x-homepage.layout title="Anmeldung bestätigen">
    <div class="mx-auto max-w-lg px-4 py-12 sm:py-16">
        @if ($confirmed)
            <div class="text-center">
                <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Vielen Dank!</h1>
                <p class="mt-3 text-gray-600">Die Anmeldung ist bestätigt. Eine Zusammenfassung mit allen Terminen haben wir dir per E-Mail geschickt.</p>
                <a href="{{ route('homepage.courses.index') }}" class="mt-8 inline-block rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Weitere Kurse ansehen</a>
            </div>
        @elseif ($registrations->isEmpty())
            <div class="text-center">
                <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Link nicht mehr gültig</h1>
                <p class="mt-3 text-gray-600">Dieser Link ist abgelaufen oder wurde schon verwendet. Falls die Plätze verfallen sind, melde dich bitte einfach neu an.</p>
                <a href="{{ route('homepage.courses.index') }}" class="mt-8 inline-block rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Zu den Kursen</a>
            </div>
        @else
            <h1 class="text-center text-3xl font-bold tracking-tight text-brand-heading">Anmeldung bestätigen</h1>

            <ul class="mt-6 flex flex-col gap-3">
                @foreach ($registrations as $registration)
                    <li class="rounded-2xl border border-gray-200 bg-white p-4">
                        <p class="font-semibold text-gray-900">{{ $registration->courseInstance->displayTitle() }}</p>
                        <p class="text-sm text-gray-600">{{ $registration->courseInstance->dates->first()?->formattedRange() }}</p>
                        <ul class="mt-2 text-sm text-gray-700">
                            @foreach ($registration->activeEnrollments as $enrollment)
                                <li>{{ $enrollment->displayKidName() }} – {{ $enrollment->status === \App\Enums\EnrollmentStatus::Offered ? 'Platz angeboten' : $enrollment->status->getLabel() }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>

            <form method="POST" action="{{ route('homepage.registration.confirm', $token) }}" class="mt-8">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Jetzt verbindlich bestätigen</button>
            </form>
        @endif
    </div>
</x-homepage.layout>
