<!DOCTYPE html>
<html lang="de" style="{{ \App\Services\Homepage\DesignRoles::style(\App\Models\DesignTheme::rootCssVariables()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle() }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    @if ($isPreview)
        <meta name="robots" content="noindex, nofollow">
    @endif
    @if ($settings->homepage_tagline)
        <meta name="description" content="{{ $settings->homepage_tagline }}">
    @endif
    <x-feed-links />
    @vite(['resources/css/homepage.css', 'resources/js/homepage.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-page font-sans text-ink antialiased" data-design-role="page-bg">
    @if ($isPreview)
        <div class="sticky top-0 z-50 bg-ink px-4 py-2 text-center text-sm font-semibold text-white">Vorschau – so würde es aussehen. Noch nicht gespeicherte Änderungen sind enthalten.</div>
    @endif
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

                <nav class="hidden items-center text-[0.9375rem] font-light text-nav-link lg:flex" aria-label="Hauptnavigation" data-design-role="nav-link">
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
                        '[--ribbon-color:var(--color-ribbon-donation)]' => $ribbon['isDonation'],
                        '[--ribbon-color:var(--color-ribbon-newsletter)]' => ! $ribbon['isDonation'],
                    ]) data-design-role="{{ $ribbon['isDonation'] ? 'ribbon-donation' : 'ribbon-newsletter' }}">
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

    <footer class="bg-footer text-footer-ink shadow-[0_-12px_24px_-12px_rgb(0_0_0/0.35)]" data-design-role="footer-bg">
        @if ($isEditingInline && $canArrangeFooter)
            <div class="mx-auto max-w-6xl px-4 py-10 text-sm sm:px-6">
                <livewire:homepage.footer-editor />
            </div>
        @else
            <div class="mx-auto grid max-w-6xl grid-cols-1 gap-8 px-4 py-10 text-sm sm:grid-cols-2 sm:px-6 lg:grid-cols-6">
                @foreach ($settings->footerBlocks() as $block)
                    <div class="{{ \App\Livewire\Homepage\FooterEditor::widthClass($block['data']['width'] ?? 2) }}">
                        <x-homepage.footer.block :block="$block" :settings="$settings" />
                    </div>
                @endforeach
            </div>
        @endif
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="border-t border-white/15 py-4 text-xs text-footer-muted">{{ $settings->copyrightText() }}</p>
        </div>
    </footer>

    @if ($showsToolbar())
        <div class="fixed right-4 bottom-4 z-50 flex items-center gap-2 rounded-xl bg-ink/90 p-2 text-sm text-white shadow-lg backdrop-blur" data-design-panel>
            @if ($isDesigning)
                <span class="hidden px-2 sm:inline">Design-Modus</span>
                <a href="{{ request()->fullUrlWithoutQuery('design') }}" class="rounded-lg bg-white px-3 py-1.5 font-semibold text-ink hover:bg-gray-100">Fertig</a>
            @elseif ($isEditingInline)
                <span class="hidden px-2 sm:inline">Bearbeiten-Modus</span>
                <a href="{{ request()->fullUrlWithoutQuery(['bearbeiten', 'anordnen']) }}" class="rounded-lg bg-white px-3 py-1.5 font-semibold text-ink hover:bg-gray-100">Fertig</a>
            @else
                @if ($canEditInline)
                    <a href="{{ request()->fullUrlWithQuery(['bearbeiten' => 1]) }}" class="rounded-lg bg-white px-3 py-1.5 font-semibold text-ink hover:bg-gray-100">Bearbeiten</a>
                @endif
                @if ($canDesign)
                    <a href="{{ request()->fullUrlWithQuery(['design' => 1]) }}" class="rounded-lg bg-white px-3 py-1.5 font-semibold text-ink hover:bg-gray-100">Farben gestalten</a>
                @endif
            @endif
            <a href="{{ route('filament.admin.pages.dashboard') }}" class="rounded-lg px-3 py-1.5 hover:bg-white/15">Cockpit</a>
        </div>
    @endif

    @if ($isDesigning)
        <livewire:homepage.design-editor />
    @endif

    @if ($isEditingInline)
        <div
            x-data="inlineEditor"
            x-on:open-inline-editor.window="open($event.detail.url)"
            x-on:message.window="receive($event)"
        >
            <dialog x-ref="dialog" x-on:close="url = null" class="m-auto h-[90vh] w-[min(64rem,calc(100vw-2rem))] max-w-none overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-ink/60">
                <div class="flex h-full flex-col">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2 text-sm">
                        <span class="font-semibold">Direkt bearbeiten</span>
                        <button type="button" x-on:click="$refs.dialog.close()" class="rounded px-2 py-1 hover:bg-gray-100" aria-label="Schließen">✕</button>
                    </div>
                    <template x-if="url">
                        <iframe x-bind:src="url" class="w-full grow" title="Bearbeiten"></iframe>
                    </template>
                </div>
            </dialog>
        </div>
    @endif

    @livewireScripts
</body>
</html>
