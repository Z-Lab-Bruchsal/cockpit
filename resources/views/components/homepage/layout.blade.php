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
    <header x-data="{ open: false }" class="relative z-40 px-2 pt-2 sm:px-3 sm:pt-3 lg:pt-7">
        <div class="key-card rounded-2xl">
            <div class="flex min-h-20 items-center justify-center gap-8 px-6 py-3 lg:h-[5.75rem] lg:justify-start lg:py-0 lg:pr-[7.5%] lg:pl-[8.8%]">
                <a href="{{ route('homepage.index') }}" class="flex min-w-0 shrink-0 items-center">
                    @if ($settings->logo_path)
                        <img src="{{ Storage::disk('public')->url($settings->logo_path) }}" alt="{{ $settings->logo_alt }}" class="h-auto w-[200px] lg:w-40">
                    @else
                        <span class="truncate text-2xl font-bold text-brand-heading">{{ $settings->homepageTitle() }}</span>
                    @endif
                </a>

                <nav class="hidden items-center text-[0.9375rem] font-light text-brand-heading lg:flex" aria-label="Hauptnavigation">
                    @foreach ($mainLinks() as $link)
                        @unless ($loop->first)
                            <span class="px-1 text-[#5a5a5a]" aria-hidden="true">|</span>
                        @endunless
                        <a href="{{ $link['url'] }}" @class([
                            'relative after:absolute after:inset-x-0 after:-bottom-9 after:h-[0.2em] after:bg-brand hover:font-bold',
                            'after:hidden hover:after:block' => ! $link['active'],
                            'font-bold' => $link['active'],
                        ]) @if ($link['active']) aria-current="page" @endif>{{ $link['label'] }}</a>
                    @endforeach
                </nav>

                <a href="{{ $accountUrl }}" @class([
                    'relative ml-auto hidden text-[0.9375rem] font-light text-brand-heading after:absolute after:inset-x-0 after:-bottom-9 after:h-[0.2em] after:bg-brand hover:font-bold lg:block',
                    'font-bold' => $isAccountPage,
                    'after:hidden hover:after:block' => ! $isAccountPage,
                ])>{{ $isLoggedIn ? 'Mein Konto' : 'Anmelden' }}</a>
            </div>
        </div>

        @if ($ribbons = $settings->headerRibbons())
            <div class="absolute top-full right-6 -mt-2 hidden gap-5 lg:flex">
                @foreach ($ribbons as $ribbon)
                    <a href="{{ $ribbon['url'] }}" target="_blank" rel="noopener" @class([
                        'ribbon',
                        '[--ribbon-color:var(--color-brand)]' => $ribbon['isDonation'],
                        '[--ribbon-color:var(--color-ribbon-newsletter)]' => ! $ribbon['isDonation'],
                    ])>
                        <span>
                            @foreach (explode(' ', $ribbon['label'], 2) as $line)
                                <span class="block">{{ $line }}</span>
                            @endforeach
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        <button type="button" class="mt-4 flex h-12 w-full items-center justify-center rounded-[10px] border border-brand-ink bg-brand text-brand-contrast shadow-[0_4px_0_var(--color-brand-ink)] active:translate-y-[3px] active:shadow-[0_1px_0_var(--color-brand-ink)] lg:hidden" @click="open = !open" :aria-expanded="open" aria-label="Menü">
            <svg x-show="!open" class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
            <svg x-cloak x-show="open" class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>

        @if ($ribbons)
            <div class="mt-4 grid grid-cols-2 gap-3 lg:hidden">
                @foreach ($ribbons as $ribbon)
                    <a href="{{ $ribbon['url'] }}" target="_blank" rel="noopener" @class([
                        'key-button',
                        'bg-brand! text-brand-contrast!' => $ribbon['isDonation'],
                    ])>{{ $ribbon['label'] }}</a>
                @endforeach
            </div>
        @endif

        <nav x-cloak x-show="open" x-transition class="mt-3 overflow-hidden rounded-[10px] lg:hidden" aria-label="Mobile Navigation">
            <ul class="flex flex-col divide-y divide-white/85 bg-[#ebeaea] text-center text-xl font-light text-brand-heading">
                @foreach ($mainLinks() as $link)
                    <li><a href="{{ $link['url'] }}" @class(['block px-4 py-3', 'font-bold' => $link['active']]) @click="open = false">{{ $link['label'] }}</a></li>
                @endforeach
                <li><a href="{{ $accountUrl }}" class="block px-4 py-3">{{ $isLoggedIn ? 'Mein Konto' : 'Anmelden' }}</a></li>
            </ul>
        </nav>
    </header>

    <main class="grow pt-6 pb-16 lg:pt-24">
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
