@props(['member', 'privacySection' => null])

@php
    $formErrors = $errors->getBag('teamContact');
    $isFormTarget = $member->user_id !== null && (int) old('team_member_id') === (int) $member->user_id;
    $wasSent = $member->user_id !== null && (int) session('team_contact_sent') === (int) $member->user_id;
    $openOnLoad = $wasSent || ($isFormTarget && $formErrors->any());
@endphp

<div
    id="team-{{ $member->publicId() }}"
    class="scroll-mt-24"
    x-data="{
        writing: @js($isFormTarget && $formErrors->any()),
        preview: false,
        previewTimer: null,
        showPreview(delay = 150) {
            clearTimeout(this.previewTimer);
            this.previewTimer = setTimeout(() => this.preview = true, delay);
        },
        hidePreview() {
            clearTimeout(this.previewTimer);
            this.preview = false;
        },
        openCard() {
            this.hidePreview();
            this.$refs.card.showModal();
        },
    }"
    x-init="@js($openOnLoad) && $nextTick(() => $refs.card.showModal())"
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="openCard()"
        x-on:pointerenter="$event.pointerType === 'mouse' && showPreview()"
        x-on:pointerleave="hidePreview()"
        x-on:focus="$el.matches(':focus-visible') && showPreview(0)"
        x-on:blur="hidePreview()"
        x-on:keydown.escape="hidePreview()"
        aria-describedby="team-{{ $member->publicId() }}-preview"
        class="group flex w-full flex-col items-center gap-2 text-center focus:outline-none"
    >
        <x-homepage.team-avatar :member="$member" class="size-24 text-2xl ring-4 ring-transparent transition group-hover:ring-brand group-focus-visible:ring-brand sm:size-28" />
        <span class="font-semibold leading-tight">{{ $member->displayName() }}</span>
        @if ($member->team_role)
            <span class="text-sm leading-tight text-gray-600">{{ $member->team_role }}</span>
        @endif
    </button>

    {{-- Business card preview on hover / keyboard focus; the full card opens on click. --}}
    <div
        id="team-{{ $member->publicId() }}-preview"
        role="tooltip"
        x-show="preview"
        x-cloak
        x-anchor.bottom.offset.12="$refs.trigger"
        x-transition:enter="transition duration-150 ease-out"
        x-transition:enter-start="translate-y-1 opacity-0"
        x-transition:leave="transition duration-100 ease-in"
        x-transition:leave-end="opacity-0"
        class="pointer-events-none z-30 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-gray-200 bg-white text-left shadow-xl"
    >
        <div class="h-1.5 bg-brand" aria-hidden="true"></div>
        <div class="flex flex-col gap-3 p-5">
            <div class="flex items-center gap-4">
                <x-homepage.team-avatar :member="$member" class="size-16 shrink-0 text-lg" />
                <div class="flex min-w-0 flex-col">
                    <p class="font-bold leading-tight">{{ $member->displayName() }}</p>
                    @if ($member->team_role)
                        <p class="text-sm leading-tight text-gray-700">{{ $member->team_role }}</p>
                    @endif
                    <p class="mt-0.5 text-xs uppercase tracking-wide text-gray-500">{{ $member->team_area?->getLabel() }}</p>
                </div>
            </div>

            @if (filled($member->team_bio))
                <p class="line-clamp-4 text-sm leading-relaxed text-gray-800">{{ $member->team_bio }}</p>
            @endif

            @if ($member->interests->isNotEmpty())
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($member->interests->take(4) as $interest)
                        <x-homepage.interest-badge :interest="$interest" />
                    @endforeach
                </div>
            @endif

            <p class="border-t border-gray-100 pt-3 text-xs font-semibold text-brand-ink">
                {{ $member->isContactableViaHomepage() ? 'Klicken für Visitenkarte & Kontakt' : 'Klicken für die ganze Visitenkarte' }}
            </p>
        </div>
    </div>

    <dialog
        x-ref="card"
        aria-labelledby="team-{{ $member->publicId() }}-name"
        x-on:click.self="$refs.card.close()"
        class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-gray-900/60"
    >
        <div class="relative flex max-h-[85vh] flex-col gap-5 overflow-y-auto p-6 sm:p-8">
            <button type="button" x-on:click="$refs.card.close()" class="absolute right-3 top-3 rounded-full p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900" aria-label="Schließen">
                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
            </button>

            <div class="flex items-center gap-5">
                <x-homepage.team-avatar :member="$member" class="size-24 shrink-0 text-2xl" />
                <div class="flex flex-col gap-1">
                    <h3 id="team-{{ $member->publicId() }}-name" class="text-xl font-bold">{{ $member->displayName() }}</h3>
                    @if ($member->team_role)
                        <p class="text-gray-700">{{ $member->team_role }}</p>
                    @endif
                    <p class="text-sm text-gray-500">{{ $member->team_area?->getLabel() }}</p>
                </div>
            </div>

            @if (filled($member->team_bio))
                <p class="leading-relaxed text-gray-800">{!! nl2br(e($member->team_bio)) !!}</p>
            @endif

            @if ($member->interests->isNotEmpty())
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-semibold text-gray-700">Themen</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($member->interests as $interest)
                            <x-homepage.interest-badge :interest="$interest" />
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($member->user?->responsibleCourses->isNotEmpty())
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-semibold text-gray-700">Aktuelle Kurse</p>
                    <ul class="flex flex-col gap-1">
                        @foreach ($member->user->responsibleCourses as $course)
                            <li><a href="{{ $course->url() }}" class="text-brand-ink hover:underline">{{ $course->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($member->isContactableViaHomepage())
                @if ($wasSent)
                    <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-green-900" role="status">
                        <p class="font-semibold">Danke für deine Nachricht!</p>
                        <p class="mt-1 text-sm">Wir haben sie an {{ $member->displayName() }} weitergeleitet.</p>
                    </div>
                @else
                    <button type="button" x-show="! writing" x-on:click="writing = true" class="self-start rounded-lg bg-brand px-5 py-3 font-semibold text-brand-contrast hover:opacity-90">
                        Nachricht schreiben
                    </button>

                    <form method="POST" action="{{ route('homepage.team.contact', $member->user_id) }}" x-show="writing" x-cloak class="flex flex-col gap-4 border-t border-gray-200 pt-5">
                        @csrf
                        <input type="hidden" name="team_member_id" value="{{ $member->user_id }}">

                        <div class="hidden" aria-hidden="true">
                            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>

                        <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                            Name
                            <input type="text" name="name" value="{{ $isFormTarget ? old('name') : auth('contact')->user()?->name }}" required autocomplete="name" class="rounded-lg border-gray-300">
                            @if ($isFormTarget && $formErrors->has('name'))<span class="text-sm text-red-600">{{ $formErrors->first('name') }}</span>@endif
                        </label>
                        <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                            E-Mail-Adresse für die Antwort
                            <input type="email" name="email" value="{{ $isFormTarget ? old('email') : auth('contact')->user()?->email }}" required autocomplete="email" class="rounded-lg border-gray-300">
                            @if ($isFormTarget && $formErrors->has('email'))<span class="text-sm text-red-600">{{ $formErrors->first('email') }}</span>@endif
                        </label>
                        <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                            Nachricht
                            <textarea name="message" rows="4" required maxlength="5000" class="rounded-lg border-gray-300">{{ $isFormTarget ? old('message') : '' }}</textarea>
                            @if ($isFormTarget && $formErrors->has('message'))<span class="text-sm text-red-600">{{ $formErrors->first('message') }}</span>@endif
                        </label>

                        <label class="flex items-start gap-3 text-sm text-gray-700">
                            <input type="checkbox" name="privacy" value="1" @checked($isFormTarget && old('privacy')) required class="mt-0.5 rounded border-gray-300 text-brand-ink">
                            <span>
                                Ich bin einverstanden, dass meine Angaben zur Bearbeitung meiner Anfrage verwendet und an {{ $member->displayName() }} weitergeleitet werden.
                                @if ($privacySection)
                                    Mehr dazu in der <a href="{{ $privacySection->url() }}" class="text-brand-ink underline">Datenschutzerklärung</a>.
                                @endif
                            </span>
                        </label>
                        @if ($isFormTarget && $formErrors->has('privacy'))<span class="text-sm text-red-600">{{ $formErrors->first('privacy') }}</span>@endif
                        @if ($isFormTarget && $formErrors->has('website'))<span class="text-sm text-red-600">Die Nachricht konnte nicht gesendet werden.</span>@endif

                        <button type="submit" class="self-start rounded-lg bg-brand px-5 py-3 font-semibold text-brand-contrast hover:opacity-90">Nachricht senden</button>
                    </form>
                @endif
            @endif
        </div>
    </dialog>
</div>
