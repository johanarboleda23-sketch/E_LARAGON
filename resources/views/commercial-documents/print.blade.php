<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $label }} {{ $document->consecutive }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#1f2937;margin:32px auto;max-width:900px;padding:0 24px;font-size:14px}header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #0f766e;padding-bottom:16px;margin-bottom:24px}h1{font-size:22px;margin:0;color:#115e59}.muted{color:#6b7280}.meta{display:grid;grid-template-columns:1fr 1fr;gap:12px 28px;margin-bottom:24px}.meta div{border-bottom:1px solid #e5e7eb;padding-bottom:8px}table{width:100%;border-collapse:collapse}th,td{padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:left}th{background:#f3f4f6;font-size:12px;text-transform:uppercase}.numeric{text-align:right}.totals{width:280px;margin:18px 0 0 auto}.total{font-size:17px;font-weight:bold;color:#115e59;border-top:2px solid #0f766e;padding-top:10px}.notes{margin-top:24px;white-space:pre-wrap}.actions{margin-bottom:20px}.actions button{border:0;background:#0f766e;color:white;padding:9px 14px;border-radius:5px;cursor:pointer}@media print{body{margin:0 auto}.actions{display:none}}
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">Imprimir / Guardar como PDF</button></div>
    <header>
        <div>
            <p class="muted">{{ $activeCompany->name ?? config('app.name') }}</p>
            <h1>{{ $label }}</h1>
        </div>
        <strong>{{ $document->consecutive }}</strong>
    </header>

    <section class="meta">
        <div><span class="muted">{{ in_array($document->document_type, ['purchase_order', 'supplier_debit_note'], true) ? 'Proveedor' : 'Cliente' }}</span><br>{{ $document->third_party_name }}</div>
        <div><span class="muted">Fecha</span><br>{{ $document->document_date->format('d/m/Y') }}</div>
        @if ($document->referenceSale || $document->referencePurchase)
            <div><span class="muted">Factura origen</span><br>{{ $document->referenceSale?->invoice_number ?? $document->referencePurchase?->invoice_number }}</div>
        @endif
        @if ($document->third_party_document)
            <div><span class="muted">Documento</span><br>{{ $document->third_party_document }}</div>
        @endif
        @if ($document->due_date)
            <div><span class="muted">Vencimiento</span><br>{{ $document->due_date->format('d/m/Y') }}</div>
        @endif
    </section>

    <table>
        <thead><tr><th>Detalle</th><th class="numeric">Cantidad</th><th class="numeric">Valor unitario</th><th class="numeric">Total</th></tr></thead>
        <tbody>
            @foreach ($document->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="numeric">{{ $line->quantity }}</td>
                    <td class="numeric">${{ number_format($line->unit_price, 2) }}</td>
                    <td class="numeric">${{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="totals">
        <p>Subtotal <span style="float:right">${{ number_format($document->subtotal, 2) }}</span></p>
        @if ($document->discount_total > 0)
            <p>Descuentos <span style="float:right">-${{ number_format($document->discount_total, 2) }}</span></p>
        @endif
        <p>IVA <span style="float:right">${{ number_format($document->iva_total, 2) }}</span></p>
        <p class="total">Total <span style="float:right">${{ number_format($document->total, 2) }}</span></p>
    </section>

    @if ($document->notes)
        <p class="notes"><strong>Notas:</strong> {{ $document->notes }}</p>
    @endif
</body>
</html>