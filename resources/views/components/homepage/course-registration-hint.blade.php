@php
    $settings = \App\Models\CompanySetting::current();
    $channels = array_filter([
        $settings->contact_phone
            ? 'telefonisch unter <a href="tel:'.e(preg_replace('/[^0-9+]/', '', $settings->contact_phone)).'" class="font-medium text-brand hover:underline">'.e($settings->contact_phone).'</a>'
            : null,
        $settings->contact_email
            ? 'per E-Mail an <a href="mailto:'.e($settings->contact_email).'" class="font-medium text-brand hover:underline">'.e($settings->contact_email).'</a>'
            : null,
    ]);
@endphp

<p>
    @if ($channels)
        Zur Anmeldung erreichst du uns {!! implode(' oder ', $channels) !!}.
    @else
        Zur Anmeldung melde dich bitte direkt bei uns.
    @endif
</p>
