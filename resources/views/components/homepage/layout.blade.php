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
<body class="flex min-h-screen flex-col bg-page font-sans text-ink antialiased">
    <header x-data="{ open: false }" class="sticky top-0 z-40 px-2 pt-2 sm:px-4 sm:pt-4">
        <div class="key-card mx-auto max-w-7xl">
            <div class="flex h-16 items-center justify-between gap-4 px-4 sm:h-20 sm:px-8">
                <div class="flex min-w-0 items-center gap-6">
                    <a href="{{ route('homepage.index') }}" class="flex min-w-0 shrink-0 items-center gap-3">
                        @if ($settings->logo_path)
                            <img src="{{ Storage::disk('public')->url($settings->logo_path) }}" alt="{{ $settings->logo_alt }}" class="h-10 w-auto">
                        @else
                            <span class="truncate text-xl font-bold text-brand-heading">{{ $settings->homepageTitle() }}</span>
                        @endif
                    </a>

                    <nav class="hidden items-center text-sm lg:flex" aria-label="Hauptnavigation">
                        @foreach ($mainLinks() as $link)
                            @unless ($loop->first)
                                <span class="px-1.5 text-gray-400" aria-hidden="true">|</span>
                            @endunless
                            <a href="{{ $link['url'] }}" @class([
                                'text-brand-heading hover:underline',
                                'font-bold' => $link['active'],
                            ]) @if ($link['active']) aria-current="page" @endif>{{ $link['label'] }}</a>
                        @endforeach
                    </nav>
                </div>

                <a href="{{ $accountUrl }}" @class([
                    'key-button hidden text-sm lg:flex',
                    'bg-brand/10' => request()->routeIs('homepage.account', 'homepage.login', 'homepage.schools.account'),
                ])>{{ $isLoggedIn ? 'Mein Konto' : 'Anmelden' }}</a>

                <button type="button" class="rounded-md p-2 text-brand-ink hover:bg-gray-100 lg:hidden" @click="open = !open" :aria-expanded="open" aria-label="Menü">
                    <svg x-show="!open" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                    <svg x-cloak x-show="open" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <nav x-cloak x-show="open" x-transition class="border-t border-key-edge lg:hidden" aria-label="Mobile Navigation">
                <ul class="flex flex-col px-4 py-2">
                    @foreach ($mainLinks() as $link)
                        <li><a href="{{ $link['url'] }}" @class(['block py-3 font-semibold text-brand-ink', 'underline' => $link['active']]) @click="open = false">{{ $link['label'] }}</a></li>
                    @endforeach
                    <li class="border-t border-key-edge py-3"><a href="{{ $accountUrl }}" class="key-button">{{ $isLoggedIn ? 'Mein Konto' : 'Anmelden' }}</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="grow pt-6 pb-16 sm:pt-10">
        {{ $slot }}
    </main>

    <footer class="bg-footer text-footer-ink shadow-[0_-12px_24px_-12px_rgb(0_0_0/0.35)]">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 text-sm sm:px-6 lg:grid-cols-[1fr_2fr]">
            <address class="not-italic leading-relaxed text-footer-muted">
                <p class="font-semibold text-footer-ink">{{ $settings->name ?: $settings->homepageTitle() }}</p>
                @if ($settings->street)<p>{{ $settings->street }}</p>@endif
                @if ($settings->zip || $settings->city)<p>{{ trim($settings->zip.' '.$settings->city) }}</p>@endif
                @if ($settings->contact_phone)
                    <p class="mt-2"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings->contact_phone) }}" class="hover:text-footer-hover">{{ $settings->contact_phone }}</a></p>
                @endif
                @if ($settings->contact_email)
                    <p><a href="mailto:{{ $settings->contact_email }}" class="hover:text-footer-hover">{{ $settings->contact_email }}</a></p>
                @endif
            </address>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @if ($footerLinks = $settings->resolvedFooterLinks())
                    <nav aria-label="Footer" class="contents">
                        @foreach ($footerLinks as $link)
                            <a href="{{ $link['url'] }}" class="key-button" @if ($link['external']) target="_blank" rel="noopener" @endif>{{ $link['label'] }}</a>
                        @endforeach
                    </nav>
                @endif

                @foreach ($settings->resolvedSocialLinks() as $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="key-button">{{ $link['label'] }}</a>
                @endforeach
            </div>
        </div>
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="border-t border-white/15 py-4 text-xs text-footer-muted">{{ $settings->copyrightText() }}</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
