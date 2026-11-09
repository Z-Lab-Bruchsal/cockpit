@props(['instances', 'tabs' => false, 'bookable' => true])

@php
    $participations = $instances->map(fn ($instance) => $instance->participation)->unique(fn ($participation) => $participation->value)->values();
    $showTabs = $tabs && $participations->count() > 1;
@endphp

@if ($instances->isEmpty())
    <p class="key-card px-6 py-8 text-center text-gray-500">Gerade sind keine Angebote geplant. Schau bald wieder vorbei!</p>
@else
    <div x-data="{ tab: 'all' }" {{ $attributes->class('flex flex-col gap-5') }}>
        @if ($showTabs)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3" role="tablist" aria-label="Angebote filtern">
                @foreach (['all' => 'Alle Angebote'] + collect(\App\Enums\CourseParticipation::cases())->filter(fn ($case) => $participations->contains($case))->mapWithKeys(fn ($case) => [$case->value => $case->tabLabel()])->all() as $value => $tabLabel)
                    <button
                        type="button"
                        role="tab"
                        x-on:click="tab = @js($value)"
                        x-bind:aria-selected="tab === @js($value)"
                        x-bind:class="tab === @js($value) ? 'bg-brand-ink! text-white! border-transparent!' : ''"
                        class="key-button text-sm"
                    >{{ $tabLabel }}</button>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-flow-dense lg:grid-cols-12">
            @foreach ($instances as $instance)
                <x-homepage.offer-card
                    :instance="$instance"
                    :bookable="$bookable"
                    x-show="tab === 'all' || tab === {{ \Illuminate\Support\Js::from($instance->participation->value) }}"
                />
            @endforeach
        </div>
    </div>
@endif
