<div
    role="alert"
    style="position: sticky; top: 0; z-index: 50; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.5rem 1rem; background: #dc2626; color: #fff; font-size: 0.875rem; font-weight: 600; text-align: center;"
>
    <span>{{ __('filament-mail-test-mode::messages.banner', ['recipient' => $recipient ?? __('filament-mail-test-mode::messages.no_recipient')]) }}</span>

    @if ($pageUrl)
        <a href="{{ $pageUrl }}" style="color: #fff; text-decoration: underline;">
            {{ __('filament-mail-test-mode::messages.banner_link') }}
        </a>
    @endif
</div>
