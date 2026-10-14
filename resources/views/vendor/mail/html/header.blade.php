@props(['url', 'logo' => null, 'logoAlt' => null])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logo)
<img src="{{ $logo }}" alt="{{ $logoAlt }}" style="max-height: 64px; max-width: 240px; height: auto; width: auto; border: 0;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
