@php($labels = array_keys($tabs))

<div
    x-data="{ tab: @js($labels[0]), mobile: false }"
    class="flex flex-col gap-3"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-filament::tabs>
            @foreach ($tabs as $label => $url)
                <x-filament::tabs.item
                    :alpine-active="'tab === ' . \Illuminate\Support\Js::from($label)"
                    x-on:click="tab = {{ \Illuminate\Support\Js::from($label) }}"
                >
                    {{ $label }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <x-filament::tabs>
            <x-filament::tabs.item alpine-active="! mobile" x-on:click="mobile = false" :icon="\Filament\Support\Icons\Heroicon::OutlinedComputerDesktop">
                Desktop
            </x-filament::tabs.item>
            <x-filament::tabs.item alpine-active="mobile" x-on:click="mobile = true" :icon="\Filament\Support\Icons\Heroicon::OutlinedDevicePhoneMobile">
                Handy
            </x-filament::tabs.item>
        </x-filament::tabs>
    </div>

    <div class="flex justify-center rounded-xl bg-gray-100 p-2 dark:bg-white/5">
        @foreach ($tabs as $label => $url)
            <iframe
                x-show="tab === {{ \Illuminate\Support\Js::from($label) }}"
                x-cloak
                src="{{ $url }}"
                title="Vorschau: {{ $label }}"
                loading="lazy"
                class="h-[75vh] rounded-lg bg-white shadow-sm transition-all"
                x-bind:style="mobile ? 'width: 390px' : 'width: 100%'"
            ></iframe>
        @endforeach
    </div>
</div>
