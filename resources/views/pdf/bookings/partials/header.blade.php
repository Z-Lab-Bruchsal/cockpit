@php
    $logoPath = $company->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->path($company->logo_path) : null;
@endphp
<table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
    <tr>
        <td style="width: 50%; vertical-align: top;">
            @if ($logoPath && file_exists($logoPath))
                <img src="{{ $logoPath }}" style="max-height: 60px; max-width: 220px;">
            @endif
            <div style="margin-top: 8px; font-size: 10px; color: #555555;">
                @if ($company->name)
                    <strong>{{ $company->name }}</strong><br>
                @endif
                @if ($company->street)
                    {{ $company->street }}<br>
                @endif
                @if ($company->zip || $company->city)
                    {{ trim("{$company->zip} {$company->city}") }}
                @endif
            </div>
        </td>
        @if ($recipient)
            <td style="width: 50%; vertical-align: top; text-align: right; font-size: 11px;">
                <strong>{{ $recipient->name }}</strong><br>
                @if ($recipient->street)
                    {{ $recipient->street }}<br>
                @endif
                @if ($recipient->zip || $recipient->city)
                    {{ trim("{$recipient->zip} {$recipient->city}") }}
                @endif
            </td>
        @endif
    </tr>
</table>
