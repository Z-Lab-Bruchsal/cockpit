<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1a202c; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .subtitle { color: #555555; margin-bottom: 20px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 6px 8px; font-size: 11px; text-align: left; }
        table.data th { background-color: #f0f0f0; }
        .text-right { text-align: right; }
        tfoot td { font-weight: bold; background-color: #f7fafc; }
        .signatures { margin-top: 60px; width: 100%; }
        .signatures td { width: 50%; padding-top: 40px; border-top: 1px solid #333333; font-size: 10px; }
        .footer-note { margin-top: 20px; font-size: 9px; color: #888888; }
    </style>
</head>
<body>
    @include('pdf.bookings.partials.header', ['company' => $company, 'recipient' => $recipient ?? null])

    @yield('content')

    <div class="footer-note">Erstellt am {{ $generatedAt->format('d.m.Y H:i') }} Uhr</div>
</body>
</html>
