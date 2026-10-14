@props(['topic' => null])

@php
    $contact = auth('contact')->user();
    $presetTopic = old('topic', \App\Enums\ContactFormTopic::tryFrom((string) ($topic ?? request()->query('anliegen')))?->value ?? \App\Enums\ContactFormTopic::General->value);
    $privacySection = \App\Models\Section::query()->where('slug', 'datenschutz')->where('is_visible', true)->first();
    $formErrors = $errors->getBag('contactForm');
@endphp

<div id="kontaktformular" {{ $attributes->class('scroll-mt-24') }}>
    @if (session('contact_form_sent'))
        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 text-green-900" role="status">
            <p class="font-semibold">Danke für deine Nachricht!</p>
            <p class="mt-1 text-sm">Wir melden uns so bald wie möglich bei dir.</p>
        </div>
    @else
        <form method="POST" action="{{ route('homepage.contact-form.send') }}" x-data="{ topic: @js($presetTopic) }" class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
            @csrf

            <div class="hidden" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Name
                    <input type="text" name="name" value="{{ old('name', $contact?->name) }}" required autocomplete="name" class="rounded-lg border-gray-300">
                    @if ($formErrors->has('name'))<span class="text-sm text-red-600">{{ $formErrors->first('name') }}</span>@endif
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    E-Mail-Adresse für die Antwort
                    <input type="email" name="email" value="{{ old('email', $contact?->email) }}" required autocomplete="email" class="rounded-lg border-gray-300">
                    @if ($formErrors->has('email'))<span class="text-sm text-red-600">{{ $formErrors->first('email') }}</span>@endif
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Telefon <span class="font-normal text-gray-500">(optional)</span>
                    <input type="tel" name="phone" value="{{ old('phone', $contact?->phone) }}" autocomplete="tel" class="rounded-lg border-gray-300">
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                    Anliegen
                    <select name="topic" x-model="topic" class="rounded-lg border-gray-300">
                        @foreach (\App\Enums\ContactFormTopic::cases() as $case)
                            <option value="{{ $case->value }}" @selected($presetTopic === $case->value)>{{ $case->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <label x-show="topic === @js(\App\Enums\ContactFormTopic::LostEmailAccess->value)" class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Bisherige E-Mail-Adresse, auf die du keinen Zugriff mehr hast
                <input type="email" name="old_email" value="{{ old('old_email') }}" class="rounded-lg border-gray-300">
                <span class="font-normal text-gray-500">Gib oben die neue Adresse an, unter der wir dich erreichen. Zur Sicherheit melden wir uns kurz bei dir, bevor wir die Adresse umstellen.</span>
                @if ($formErrors->has('old_email'))<span class="text-sm text-red-600">{{ $formErrors->first('old_email') }}</span>@endif
            </label>

            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Nachricht
                <textarea name="message" rows="5" required maxlength="5000" class="rounded-lg border-gray-300">{{ old('message') }}</textarea>
                @if ($formErrors->has('message'))<span class="text-sm text-red-600">{{ $formErrors->first('message') }}</span>@endif
            </label>

            <label class="flex items-start gap-3 text-sm text-gray-700">
                <input type="checkbox" name="privacy" value="1" @checked(old('privacy')) required class="mt-0.5 rounded border-gray-300 text-brand">
                <span>
                    Ich bin einverstanden, dass meine Angaben zur Bearbeitung meiner Anfrage verwendet werden.
                    @if ($privacySection)
                        Mehr dazu in der <a href="{{ $privacySection->url() }}" class="text-brand underline">Datenschutzerklärung</a>.
                    @endif
                </span>
            </label>
            @if ($formErrors->has('privacy'))<span class="text-sm text-red-600">{{ $formErrors->first('privacy') }}</span>@endif
            @if ($formErrors->has('website'))<span class="text-sm text-red-600">Die Nachricht konnte nicht gesendet werden.</span>@endif

            <button type="submit" class="self-start rounded-lg bg-brand px-5 py-3 font-semibold text-brand-contrast hover:opacity-90">Nachricht senden</button>
        </form>
    @endif
</div>
