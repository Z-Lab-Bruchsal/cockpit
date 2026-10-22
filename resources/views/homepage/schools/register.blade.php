<x-homepage.layout title="Schule registrieren">
    <div class="mx-auto max-w-xl px-4 py-12 sm:py-16">
        <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Schule registrieren</h1>

        @if (session('school_registration_sent'))
            <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-5 text-green-900" role="status">
                <p class="font-semibold">Bitte prüfen Sie das Postfach!</p>
                <p class="mt-1 text-sm">Wir haben eine E-Mail an <strong>{{ session('school_registration_sent') }}</strong> geschickt. Mit dem Link darin bestätigen Sie das Schulkonto und melden sich an.</p>
            </div>
        @else
            <p class="mt-3 text-gray-600">Das Schulkonto steht für die ganze Schule. Bitte verwenden Sie eine <strong>Funktionsadresse</strong> der Schule (z. B. Sekretariat), keine persönliche Adresse. Ihre Lehrkräfte laden Sie danach im Schulkonto ein.</p>
        @endif

        <form method="POST" action="{{ route('homepage.schools.register.send') }}" class="mt-8 grid gap-4 sm:grid-cols-2">
            @csrf
            <div class="hidden" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700 sm:col-span-2">
                Name der Schule
                <input type="text" name="name" value="{{ old('name') }}" required autocomplete="organization" class="rounded-lg border-gray-300">
                @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700 sm:col-span-2">
                Straße und Hausnummer
                <input type="text" name="street" value="{{ old('street') }}" required autocomplete="street-address" class="rounded-lg border-gray-300">
                @error('street')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                PLZ
                <input type="text" name="zip" value="{{ old('zip') }}" required autocomplete="postal-code" class="rounded-lg border-gray-300">
                @error('zip')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Ort
                <input type="text" name="city" value="{{ old('city') }}" required autocomplete="address-level2" class="rounded-lg border-gray-300">
                @error('city')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700 sm:col-span-2">
                Funktionsadresse der Schule
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="rounded-lg border-gray-300">
                @error('email')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Telefon <span class="font-normal text-gray-500">(optional)</span>
                <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" class="rounded-lg border-gray-300">
                @error('phone')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Ansprechpartner*in <span class="font-normal text-gray-500">(optional)</span>
                <input type="text" name="contact_person" value="{{ old('contact_person') }}" autocomplete="name" class="rounded-lg border-gray-300">
                @error('contact_person')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="flex items-start gap-3 text-sm text-gray-700 sm:col-span-2">
                <input type="checkbox" name="accepts_privacy" value="1" @checked(old('accepts_privacy')) class="mt-0.5 rounded border-gray-300 text-brand-ink">
                <span>Ich bin einverstanden, dass die Angaben zur Organisation der Kurse gespeichert werden.</span>
            </label>
            @error('accepts_privacy')<span class="text-sm text-red-600 sm:col-span-2">{{ $message }}</span>@enderror
            @error('website')<p class="text-sm text-red-600 sm:col-span-2">Die Anfrage konnte nicht verarbeitet werden.</p>@enderror

            <button type="submit" class="rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90 sm:col-span-2">Schule registrieren</button>
        </form>

        <p class="mt-6 text-sm text-gray-500">Ihre Schule ist schon registriert? <a href="{{ route('homepage.login', ['weiter' => route('homepage.schools.account', absolute: false)]) }}" class="font-medium text-brand-ink hover:underline">Anmelden</a> – Lehrkräfte bekommen eine Einladung von ihrer Schule.</p>
    </div>
</x-homepage.layout>
