<div class="flex flex-col items-start gap-4 rounded-2xl bg-brand px-6 py-8 text-brand-contrast sm:flex-row sm:items-center sm:justify-between sm:px-10">
    <div>
        <p class="text-xl font-semibold">{{ $data['heading'] ?? '' }}</p>
        @if (filled($data['text'] ?? null))
            <p class="mt-1 opacity-85">{{ $data['text'] }}</p>
        @endif
    </div>
    @if (filled($data['button_url'] ?? null))
        <a href="{{ $data['button_url'] }}" class="shrink-0 rounded-lg bg-white px-5 py-3 font-semibold text-gray-900 shadow hover:bg-gray-100">{{ $data['button_label'] ?? 'Mehr' }}</a>
    @endif
</div>
