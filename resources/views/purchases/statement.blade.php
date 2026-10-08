<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estado de cartera · {{ $provider }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#1f2937;margin:32px auto;max-width:900px;padding:0 24px;font-size:14px}
        header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #be185d;padding-bottom:16px;margin-bottom:24px}
        h1{font-size:20px;margin:0;color:#831843}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:left}
        th{background:#f3f4f6;font-size:12px;text-transform:uppercase}
        .numeric{text-align:right}
        .total{font-size:17px;font-weight:bold;color:#831843;border-top:2px solid #be185d;padding-top:10px;text-align:right;margin-top:14px}
        .trashed{color:#9ca3af;text-decoration:line-through}
        .back{border:0;background:#be185d;color:white;padding:9px 14px;border-radius:5px;text-decoration:none;font-size:13px}
    </style>
</head>
<body>
    <header>
        <div>
            <p style="color:#6b7280;margin:0 0 2px;">Cuentas por pagar</p>
            <h1>{{ $provider }}</h1>
        </div>
        <a class="back" href="{{ route('purchases.index') }}">⌂ Compras</a>
    </header>

    <table>
        <thead><tr><th>Factura</th><th>Fecha</th><th class="numeric">Total</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse($documents as $document)
                <tr class="{{ $document->trashed() ? 'trashed' : '' }}">
                    <td><a href="{{ route('purchases.show', $document) }}" target="_blank">{{ $document->invoice_number }}</a></td>
                    <td>{{ $document->purchase_date->format('d/m/Y') }}</td>
                    <td class="numeric">${{ number_format($document->total_pagar, 2) }}</td>
                    <td>{{ $document->trashed() ? 'Eliminada' : 'Activa' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No hay facturas registradas para este proveedor.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="total">Saldo total: ${{ number_format($balance, 2) }}</p>
</body>
</html>
