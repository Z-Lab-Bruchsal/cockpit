@php($settings = \App\Models\CompanySetting::current())

<div class="grid gap-6 md:grid-cols-2">
    <x-homepage.rich-text :html="$data['intro'] ?? null" />
    <address class="not-italic leading-relaxed">
        <p class="font-semibold">{{ $settings->name ?: $settings->homepageTitle() }}</p>
        @if ($settings->street)<p>{{ $settings->street }}</p>@endif
        @if ($settings->zip || $settings->city)<p>{{ trim($settings->zip.' '.$settings->city) }}</p>@endif
        @if ($settings->contact_phone)<p class="mt-2">Telefon: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings->contact_phone) }}" class="text-brand-ink hover:underline">{{ $settings->contact_phone }}</a></p>@endif
        @if ($settings->contact_email)<p>E-Mail: <a href="mailto:{{ $settings->contact_email }}" class="text-brand-ink hover:underline">{{ $settings->contact_email }}</a></p>@endif
    </address>
</div>
