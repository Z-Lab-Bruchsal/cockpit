<!DOCTYPE html>
<html lang="de" style="{{ collect($settings->homepageCssVariables())->map(fn ($value, $name) => $name.': '.$value)->implode('; ') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle() }}</title>
    @if ($settings->homepage_tagline)
        <meta name="description" content="{{ $settings->homepage_tagline }}">
    @endif
    @vite(['resources/css/homepage.css', 'resources/js/homepage.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-white font-sans text-gray-800 antialiased">
    <header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-gray-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('homepage.index') }}" class="flex min-w-0 items-center gap-3">
                @if ($settings->logo_path)
                    <img src="{{ Storage::disk('public')->url($settings->logo_path) }}" alt="{{ $settings->logo_alt }}" class="h-9 w-auto">
                @endif
                <span class="truncate text-lg font-semibold">{{ $settings->homepageTitle() }}</span>
            </a>

            <nav class="hidden items-center gap-1 md:flex" aria-label="Hauptnavigation">
                @foreach ($navigation as $item)
                    <a href="{{ $item->url() }}" @class([
                        'rounded-md px-3 py-2 text-sm font-medium hover:bg-gray-100',
                        'bg-brand/10 text-gray-900' => request()->is($item->slug),
                    ])>{{ $item->title }}</a>
                @endforeach
                <a href="{{ route('homepage.courses.index') }}" @class([
                    'rounded-md px-3 py-2 text-sm font-medium hover:bg-gray-100',
                    'bg-brand/10 text-gray-900' => request()->routeIs('homepage.courses.*'),
                ])>Kurse</a>
                <a href="{{ route('homepage.calendar') }}" @class([
                    'rounded-md px-3 py-2 text-sm font-medium hover:bg-gray-100',
                    'bg-brand/10 text-gray-900' => request()->routeIs('homepage.calendar'),
                ])>Kalender</a>
                <a href="{{ $accountUrl }}" @class([
                    'ml-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-100',
                    'bg-brand/10 text-gray-900' => request()->routeIs('homepage.account', 'homepage.login'),
                ])>{{ $isLoggedIn ? 'Mein Konto' : 'Anmelden' }}</a>
            </nav>

            <button type="button" class="rounded-md p-2 hover:bg-gray-100 md:hidden" @click="open = !open" :aria-expanded="open" aria-label="Menü">
                <svg x-show="!open" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                <svg x-cloak x-show="open" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav x-cloak x-show="open" x-transition class="border-t border-gray-200 md:hidden" aria-label="Mobile Navigation">
            <ul class="flex flex-col px-4 py-2">
                @foreach ($navigation as $item)
                    <li><a href="{{ $item->url() }}" class="block py-3 font-medium" @click="open = false">{{ $item->title }}</a></li>
                @endforeach
                <li><a href="{{ route('homepage.courses.index') }}" class="block py-3 font-medium">Kurse</a></li>
                <li><a href="{{ route('homepage.calendar') }}" class="block py-3 font-medium">Kalender</a></li>
                <li class="border-t border-gray-200"><a href="{{ $accountUrl }}" class="block py-3 font-medium">{{ $isLoggedIn ? 'Mein Konto' : 'Anmelden' }}</a></li>
            </ul>
        </nav>
    </header>

    <main class="grow">
        {{ $slot }}
    </main>

    <footer class="bg-accent text-accent-contrast">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 text-sm sm:grid-cols-2 sm:px-6 lg:grid-cols-3">
            <address class="not-italic leading-relaxed">
                <p class="font-semibold">{{ $settings->name ?: $settings->homepageTitle() }}</p>
                @if ($settings->street)<p>{{ $settings->street }}</p>@endif
                @if ($settings->zip || $settings->city)<p>{{ trim($settings->zip.' '.$settings->city) }}</p>@endif
                @if ($settings->contact_phone)
                    <p class="mt-2"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings->contact_phone) }}" class="hover:underline">{{ $settings->contact_phone }}</a></p>
                @endif
                @if ($settings->contact_email)
                    <p><a href="mailto:{{ $settings->contact_email }}" class="hover:underline">{{ $settings->contact_email }}</a></p>
                @endif
            </address>

            @if ($footerLinks = $settings->resolvedFooterLinks())
                <nav aria-label="Footer">
                    <ul class="flex flex-col gap-2">
                        @foreach ($footerLinks as $link)
                            <li>
                                <a href="{{ $link['url'] }}" class="hover:underline" @if ($link['external']) target="_blank" rel="noopener" @endif>{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            @if ($socialLinks = $settings->resolvedSocialLinks())
                <div>
                    <p class="mb-2 font-semibold">Folge uns</p>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($socialLinks as $link)
                            <li>
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="inline-block rounded-full border border-current px-3 py-1 opacity-80 hover:opacity-100">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        <div class="border-t border-current/15">
            <p class="mx-auto max-w-6xl px-4 py-4 text-xs opacity-70 sm:px-6">{{ $settings->copyrightText() }}</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
