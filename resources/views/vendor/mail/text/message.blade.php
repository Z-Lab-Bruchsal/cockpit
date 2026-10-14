@php
    $mailSettings = \App\Models\CompanySetting::current();
    $mailTitle = $mailSettings->homepageTitle();
@endphp
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="route('homepage.index')">
            {{ $mailTitle }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            {{ $mailSettings->name ?: $mailTitle }} · {{ collect([$mailSettings->street, trim($mailSettings->zip.' '.$mailSettings->city), $mailSettings->contact_phone, $mailSettings->contact_email])->filter()->implode(' · ') }}
            {{ route('homepage.index') }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
