<x-mail::message>
@if (filled($contactName))
Hallo {{ $contactName }},
@else
Hallo,
@endif

@foreach (preg_split('/\R{2,}/', trim($participantMessage->body)) as $paragraph)
{!! nl2br(e($paragraph)) !!}

@endforeach
<x-mail::panel>
**{{ $instance->displayTitle() }}**@if ($instance->scheduleLabel())<br>{{ $instance->scheduleLabel() }}@endif
</x-mail::panel>

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
