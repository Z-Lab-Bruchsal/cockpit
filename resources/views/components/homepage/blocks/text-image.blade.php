<div class="grid items-center gap-6 md:grid-cols-2 md:gap-10">
    <x-homepage.rich-text :html="$data['body'] ?? null" @class(['md:order-2' => ($data['image_position'] ?? 'right') === 'left']) />
    @if (filled($data['image'] ?? null))
        <img src="{{ Storage::disk('public')->url($data['image']) }}" alt="{{ $data['alt'] ?? '' }}" loading="lazy" class="w-full rounded-xl object-cover">
    @endif
</div>
