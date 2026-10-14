<x-homepage.layout title="Anmeldung verwalten">
    <div class="mx-auto max-w-lg px-4 py-12 sm:py-16">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Deine Anmeldung</h1>

        @if (session('status'))
            <p class="mt-6 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-lg font-semibold text-gray-900">{{ $registration->courseInstance->displayTitle() }}</p>
            <ul class="mt-2 text-sm text-gray-600">
                @foreach ($registration->courseInstance->dates as $date)
                    <li>{{ $date->formattedRange() }}</li>
                @endforeach
            </ul>

            <ul class="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                @foreach ($registration->enrollments as $enrollment)
                    <li class="flex items-center justify-between gap-3 py-3 text-sm">
                        <span>
                            <span class="font-medium text-gray-900">{{ $enrollment->displayKidName() }}</span>
                            <span class="text-gray-500">– {{ $enrollment->status->getLabel() }}</span>
                        </span>
                        @if (! in_array($enrollment->status, [\App\Enums\EnrollmentStatus::Cancelled, \App\Enums\EnrollmentStatus::Expired], true) && $registration->activeEnrollments->count() > 1)
                            <form method="POST" action="{{ route('homepage.registration.cancel', $token) }}" onsubmit="return confirm('{{ $enrollment->displayKidName() }} wirklich abmelden?')">
                                @csrf
                                <input type="hidden" name="enrollment" value="{{ $enrollment->id }}">
                                <button type="submit" class="text-gray-500 hover:text-red-600 hover:underline">Abmelden</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($registration->status->isActive())
            <form method="POST" action="{{ route('homepage.registration.cancel', $token) }}" class="mt-6" onsubmit="return confirm('Die gesamte Anmeldung wirklich stornieren?')">
                @csrf
                <button type="submit" class="w-full rounded-lg border border-red-300 px-4 py-3 font-semibold text-red-700 hover:bg-red-50">Gesamte Anmeldung stornieren</button>
            </form>
            <p class="mt-3 text-sm text-gray-500">Bitte storniere rechtzeitig, damit ein anderes Kind nachrücken kann.</p>
        @endif
    </div>
</x-homepage.layout>
