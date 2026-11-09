@php
    $mailTestMode = $this->mailTestMode();
    $setting = $mailTestMode->setting();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('filament-mail-test-mode::messages.status') }}
        </x-slot>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div>
                @if ($mailTestMode->isEnabled())
                    <x-filament::badge color="danger" icon="heroicon-o-exclamation-triangle">
                        {{ __('filament-mail-test-mode::messages.state_enabled') }}
                    </x-filament::badge>
                @else
                    <x-filament::badge color="success" icon="heroicon-o-check-circle">
                        {{ __('filament-mail-test-mode::messages.state_disabled') }}
                    </x-filament::badge>
                @endif
            </div>

            @if ($mailTestMode->isEnabled())
                <p>
                    {{ __('filament-mail-test-mode::messages.redirect_info') }}
                    <strong>{{ $mailTestMode->recipient() ?? __('filament-mail-test-mode::messages.no_recipient') }}</strong>
                </p>

                @if ($mailTestMode->isForcedByEnv())
                    <p>{{ __('filament-mail-test-mode::messages.forced_by_env') }}</p>
                @elseif ($setting->activated_at)
                    <p style="opacity: 0.7;">
                        {{ __('filament-mail-test-mode::messages.activated_at', ['date' => $setting->activated_at->format('d.m.Y H:i')]) }}
                    </p>
                @endif
            @else
                <p>{{ __('filament-mail-test-mode::messages.disabled_info') }}</p>
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
