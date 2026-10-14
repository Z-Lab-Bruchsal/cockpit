@if (filled($data['image'] ?? null))
    <figure>
        <img src="{{ Storage::disk('public')->url($data['image']) }}" alt="{{ $data['alt'] ?? '' }}" loading="lazy" class="w-full rounded-xl object-cover">
        @if (filled($data['caption'] ?? null))
            <figcaption class="mt-2 text-sm text-gray-500">{{ $data['caption'] }}</figcaption>
        @endif
    </figure>
@endif
