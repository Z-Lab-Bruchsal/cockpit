@props(['member', 'privacySection' => null])

@php
    $formErrors = $errors->getBag('teamContact');
    $isFormTarget = (int) old('team_member_id') === $member->id;
    $wasSent = (int) session('team_contact_sent') === $member->id;
    $openOnLoad = $wasSent || ($isFormTarget && $formErrors->any());
@endphp

<div
    id="team-{{ $member->id }}"
    class="scroll-mt-24"
    x-data="{ writing: @js($isFormTarget && $formErrors->any()) }"
    x-init="@js($openOnLoad) && $nextTick(() => $refs.card.showModal())"
>
    <button type="button" x-on:click="$refs.card.showModal()" class="group flex w-full flex-col items-center gap-2 text-center focus:outline-none">
        <x-homepage.team-avatar :member="$member" class="size-24 text-2xl ring-4 ring-transparent transition group-hover:ring-brand group-focus-visible:ring-brand sm:size-28" />
        <span class="font-semibold leading-tight">{{ $member->name }}</span>
        @if ($member->team_role)
            <span class="text-sm leading-tight text-gray-600">{{ $member->team_role }}</span>
        @endif
    </button>

    <dialog
        x-ref="card"
        aria-labelledby="team-{{ $member->id }}-name"
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
                    <h3 id="team-{{ $member->id }}-name" class="text-xl font-bold">{{ $member->name }}</h3>
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

            @if ($member->responsibleCourses->isNotEmpty())
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-semibold text-gray-700">Aktuelle Kurse</p>
                    <ul class="flex flex-col gap-1">
                        @foreach ($member->responsibleCourses as $course)
                            <li><a href="{{ $course->url() }}" class="text-brand-ink hover:underline">{{ $course->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($member->isContactableViaHomepage())
                @if ($wasSent)
                    <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-green-900" role="status">
                        <p class="font-semibold">Danke für deine Nachricht!</p>
                        <p class="mt-1 text-sm">Wir haben sie an {{ $member->name }} weitergeleitet.</p>
                    </div>
                @else
                    <button type="button" x-show="! writing" x-on:click="writing = true" class="self-start rounded-lg bg-brand px-5 py-3 font-semibold text-brand-contrast hover:opacity-90">
                        Nachricht schreiben
                    </button>

                    <form method="POST" action="{{ route('homepage.team.contact', $member) }}" x-show="writing" x-cloak class="flex flex-col gap-4 border-t border-gray-200 pt-5">
                        @csrf
                        <input type="hidden" name="team_member_id" value="{{ $member->id }}">

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
                                Ich bin einverstanden, dass meine Angaben zur Bearbeitung meiner Anfrage verwendet und an {{ $member->name }} weitergeleitet werden.
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
