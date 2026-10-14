@php
    $mailSettings = \App\Models\CompanySetting::current();
    $mailTitle = $mailSettings->homepageTitle();
    $mailAddress = collect([$mailSettings->street, trim($mailSettings->zip.' '.$mailSettings->city)])->filter()->implode(', ');
    $mailContact = collect([
        $mailSettings->contact_phone ? 'Telefon '.$mailSettings->contact_phone : null,
        $mailSettings->contact_email ? '['.$mailSettings->contact_email.'](mailto:'.$mailSettings->contact_email.')' : null,
    ])->filter()->implode(' · ');
    $mailLegalNotice = \App\Models\Section::query()->where('slug', 'impressum')->where('is_visible', true)->first();
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="route('homepage.index')" :logo="$mailSettings->logo_path ? Storage::disk('public')->url($mailSettings->logo_path) : null" :logo-alt="$mailSettings->logo_alt ?: $mailTitle">
{{ $mailTitle }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
**{{ $mailTitle }}**<br>
@if ($mailAddress){{ $mailAddress }}<br>@endif
@if ($mailContact){!! $mailContact !!}<br>@endif
[{{ parse_url(route('homepage.index'), PHP_URL_HOST) }}]({{ route('homepage.index') }})@if ($mailLegalNotice) · [Impressum]({{ $mailLegalNotice->url() }})@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
