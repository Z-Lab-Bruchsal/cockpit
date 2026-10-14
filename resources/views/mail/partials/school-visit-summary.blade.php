@php
    $instance = $registration->courseInstance;
    $class = $registration->schoolClass();
@endphp
<x-mail::table>
| | |
|:--|:--|
| **Kurs** | {{ str_replace('|', '/', $instance->displayTitle()) }} |
@foreach ($instance->dates as $date)
| **{{ $loop->count > 1 ? 'Termin '.$loop->iteration : 'Termin' }}** | {{ $date->formattedRange() }} |
@endforeach
| **Ort** | {{ $registration->venue?->getLabel() }}{{ filled($registration->visitAddress()) ? ': '.str_replace('|', '/', $registration->visitAddress()) : '' }} |
| **Schule** | {{ str_replace('|', '/', (string) $registration->school?->name) }} |
| **Klasse** | {{ str_replace('|', '/', $class['class_name']) }} |
| **Schülerzahl** | {{ $class['students'] }} |
| **Lehrkraft** | {{ str_replace('|', '/', $class['teacher_name']) }} |
@if ($class['topic'])
| **Thema / Anmerkung** | {{ str_replace('|', '/', \Illuminate\Support\Str::squish($class['topic'])) }} |
@endif
</x-mail::table>
