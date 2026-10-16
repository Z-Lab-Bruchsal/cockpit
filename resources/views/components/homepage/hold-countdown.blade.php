@props(['expiresAt', 'restartUrl', 'formal' => false])

@php($secondsLeft = max(0, (int) now()->diffInSeconds($expiresAt, absolute: false)))

{{-- Sticky countdown of a running booking. When the time is up, an overlay blocks the form; the server checks the time again on submit. --}}
<div
    x-data="{
        remaining: Math.max(0, Math.floor(({{ $expiresAt->getTimestamp() }} * 1000 - Date.now()) / 1000)),
        init() {
            const timer = setInterval(() => {
                this.remaining = Math.max(0, this.remaining - 1);
                if (this.remaining === 0) clearInterval(timer);
            }, 1000);
        },
        get display() { return Math.floor(this.remaining / 60) + ':' + String(this.remaining % 60).padStart(2, '0'); },
    }"
    {{ $attributes->class('sticky top-0 z-30 -mx-4 mb-6 sm:mx-0') }}
>
    <div
        class="flex items-center gap-3 border-y p-4 text-sm shadow-sm sm:rounded-2xl sm:border"
        :class="remaining < 120 ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-900'"
        role="timer"
        aria-live="off"
    >
        <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        <span>
            Noch <strong class="font-mono text-base tabular-nums" x-text="display">{{ intdiv($secondsLeft, 60) }}:{{ str_pad((string) ($secondsLeft % 60), 2, '0', STR_PAD_LEFT) }}</strong>
            {{ $formal ? 'Minuten, um die Buchung abzuschicken. Danach wird der Termin wieder freigegeben.' : 'Minuten, um die Buchung abzuschicken. Danach werden die Plätze wieder freigegeben.' }}
        </span>
    </div>

    <div
        x-show="remaining === 0"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="hold-expired-title"
    >
        <div class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-xl">
            <h2 id="hold-expired-title" class="text-xl font-bold text-gray-900">Die Zeit ist abgelaufen</h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ $formal ? 'Der Termin wurde wieder freigegeben. Bitte starten Sie die Buchung neu.' : 'Die Plätze wurden wieder freigegeben. Bitte starte die Buchung neu.' }}
            </p>
            <a href="{{ $restartUrl }}" class="mt-6 inline-block rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Neu starten</a>
        </div>
    </div>
</div>
