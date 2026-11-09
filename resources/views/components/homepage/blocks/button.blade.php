@php
    $url = \App\Services\Homepage\LinkTarget::resolve($data);
@endphp

@if ($url !== null && filled($data['label'] ?? null))
    <div @class([
        'flex',
        'justify-center' => ($data['align'] ?? 'center') === 'center',
        'justify-end' => ($data['align'] ?? 'center') === 'right',
    ])>
        <a href="{{ $url }}" @class([
            'px-5 py-3 font-bold',
            'key-button' => ($data['variant'] ?? 'outline') === 'outline',
            'rounded-lg bg-brand text-brand-contrast shadow hover:opacity-90' => ($data['variant'] ?? 'outline') === 'filled',
        ]) data-design-role="{{ ($data['variant'] ?? 'outline') === 'outline' ? 'button-ink' : 'brand' }}">{{ $data['label'] }}</a>
    </div>
@endif
