<x-homepage.layout title="Zeit abgelaufen">
    <div class="mx-auto max-w-lg px-4 py-12 text-center sm:py-16">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Die Zeit ist abgelaufen</h1>
        <p class="mt-3 text-gray-600">
            @if ($isSchool)
                Diese Buchung wurde nicht rechtzeitig abgeschickt oder abgebrochen. Der Termin wurde wieder freigegeben – bitte starten Sie die Buchung neu.
            @else
                Diese Buchung wurde nicht rechtzeitig abgeschickt oder abgebrochen. Die Plätze wurden wieder freigegeben – bitte starte die Buchung neu.
            @endif
        </p>
        <a href="{{ $backUrl }}" class="mt-8 inline-block rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Neu starten</a>
    </div>
</x-homepage.layout>
