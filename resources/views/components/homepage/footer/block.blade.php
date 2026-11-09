@props(['block', 'settings'])

{{-- One element of the footer grid, see CompanySetting::footerBlocks(). --}}
@php
    $data = $block['data'] ?? [];
@endphp

@switch($block['type'])
    @case('text')
        @if (filled(strip_tags((string) ($data['body'] ?? ''), '<img>')))
            <div class="prose prose-sm max-w-none text-footer-muted prose-headings:text-footer-ink prose-strong:text-footer-ink prose-a:text-footer-ink hover:prose-a:text-footer-hover">
                {{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make($data['body'])->fileAttachmentsDisk('public')->fileAttachmentsVisibility('public') }}
            </div>
        @endif
        @break

    @case('address')
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
        @break

    @case('links')
        @if ($footerLinks = $settings->resolvedFooterLinks())
            <nav aria-label="Footer" class="grid gap-3">
                @foreach ($footerLinks as $link)
                    <a href="{{ $link['url'] }}" class="key-button" @if ($link['external']) target="_blank" rel="noopener" @endif>{{ $link['label'] }}</a>
                @endforeach
            </nav>
        @endif
        @break

    @case('social')
        @if ($socialLinks = $settings->resolvedSocialLinks())
            <div class="grid gap-3">
                @foreach ($socialLinks as $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="key-button">{{ $link['label'] }}</a>
                @endforeach
            </div>
        @endif
        @break
@endswitch
