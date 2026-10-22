<x-homepage.layout title="Anmelden">
    <div class="mx-auto max-w-md px-4 py-12 sm:py-16">
        <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Anmelden oder registrieren</h1>

        @if (session('magic_link_sent'))
            <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-5 text-green-900" role="status">
                <p class="font-semibold">Schau in dein Postfach!</p>
                <p class="mt-1 text-sm">Wir haben einen Anmeldelink an <strong>{{ session('magic_link_sent') }}</strong> geschickt. Er ist 30 Minuten gültig. Falls nichts ankommt, prüfe bitte auch den Spam-Ordner.</p>
            </div>
        @else
            <p class="mt-3 text-gray-600">Du brauchst kein Passwort: Gib deine E-Mail-Adresse ein und wir schicken dir einen Link, mit dem du dich anmeldest. Bist du neu bei uns, wird damit gleich dein Konto angelegt.</p>
        @endif

        <form method="POST" action="{{ route('homepage.login.send') }}" class="mt-8 flex flex-col gap-4">
            @csrf
            <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">

            <div class="hidden" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                E-Mail-Adresse
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="rounded-lg border-gray-300">
                @error('email')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Dein Name <span class="font-normal text-gray-500">(nur bei der ersten Anmeldung nötig)</span>
                <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" class="rounded-lg border-gray-300">
                @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>

            @error('website')<p class="text-sm text-red-600">Die Anfrage konnte nicht verarbeitet werden.</p>@enderror

            <button type="submit" class="rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Anmeldelink schicken</button>
        </form>

        <p class="mt-6 text-sm text-gray-500">Kein Zugriff mehr auf die E-Mail-Adresse deines Kontos? <a href="{{ route('homepage.contact-form', ['anliegen' => \App\Enums\ContactFormTopic::LostEmailAccess->value]) }}" class="font-medium text-brand-ink hover:underline">Schreib uns</a>.</p>

        <p class="mt-3 text-sm text-gray-500">Ein Konto ist freiwillig. Für eine Kursanmeldung brauchst du keins – mit Konto musst du deine Daten aber nur einmal eingeben.</p>
    </div>
</x-homepage.layout>
