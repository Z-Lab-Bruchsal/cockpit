<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1a202c; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 24px; }
        .subtitle { color: #555555; margin-bottom: 16px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 5px 6px; font-size: 10px; text-align: left; vertical-align: top; }
        table.data th { background-color: #f0f0f0; }
        .text-right { text-align: right; }
        tfoot td { font-weight: bold; background-color: #f7fafc; }
        .footer-note { margin-top: 20px; font-size: 9px; color: #888888; }
    </style>
</head>
<body>
    @include('pdf.bookings.partials.header', ['company' => $company, 'recipient' => null])

    <h1>Bestellungen</h1>
    <div class="subtitle">{{ $from->format('d.m.Y') }} – {{ $to->copy()->subSecond()->format('d.m.Y') }} · {{ $orders->count() }} {{ $orders->count() === 1 ? 'Position' : 'Positionen' }}</div>

    @if ($orders->isEmpty())
        <p>In diesem Zeitraum wurde nichts bestellt.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Bestellt am</th>
                    <th>Händler</th>
                    <th>Bestellnummer</th>
                    <th>Artikel</th>
                    <th class="text-right">Anzahl</th>
                    <th class="text-right">Preis</th>
                    <th>Angefragt von</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td>{{ $order->orderdatetime?->format('d.m.Y') }}</td>
                        <td>{{ $order->merchant?->name ?? '–' }}</td>
                        <td>{{ $order->order_number ?? '–' }}</td>
                        <td>{{ $order->name }}</td>
                        <td class="text-right">{{ $order->count }}</td>
                        <td class="text-right">{{ $money($order->total_price !== null ? (float) $order->total_price : null) }}</td>
                        <td>{{ $order->user?->name }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">Gesamt</td>
                    <td class="text-right">{{ $money($total) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <h2>Summen je Händler</h2>
        <table class="data" style="width: 50%;">
            <tbody>
                @foreach ($totals as $merchant => $sum)
                    <tr>
                        <td>{{ $merchant }}</td>
                        <td class="text-right">{{ $money($sum) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer-note">Erstellt am {{ $generatedAt->format('d.m.Y H:i') }} Uhr</div>
</body>
</html>
