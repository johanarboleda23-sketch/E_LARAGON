<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documento soporte {{ $document->consecutive }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#1f2937;margin:32px auto;max-width:900px;padding:0 24px;font-size:14px}header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #db2777;padding-bottom:16px;margin-bottom:24px}h1{font-size:22px;margin:0;color:#831843}.muted{color:#6b7280}.meta{display:grid;grid-template-columns:1fr 1fr;gap:12px 28px;margin-bottom:24px}.meta div{border-bottom:1px solid #e5e7eb;padding-bottom:8px}.totals{width:280px;margin:18px 0 0 auto}.total{font-size:17px;font-weight:bold;color:#831843;border-top:2px solid #db2777;padding-top:10px}.actions{margin-bottom:20px}.actions a,.actions button{border:0;background:#db2777;color:white;padding:9px 14px;border-radius:5px;cursor:pointer;text-decoration:none;font-size:13px}@media print{body{margin:0 auto}.actions{display:none}}
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()">Imprimir / Guardar como PDF</button>
        @if($document->accounting_voucher_id)
            <a target="_blank" href="{{ route('accounting.vouchers.accounting', $document->accounting_voucher_id) }}">Ver asiento contable</a>
        @endif
    </div>
    <header>
        <div>
            <p class="muted">{{ config('app.name') }}</p>
            <h1>Documento soporte</h1>
        </div>
        <strong>{{ $document->consecutive }}</strong>
    </header>

    <section class="meta">
        <div><span class="muted">Proveedor</span><br>{{ $document->supplier->name }}</div>
        <div><span class="muted">Fecha</span><br>{{ $document->document_date->format('d/m/Y') }}</div>
        <div><span class="muted">Concepto</span><br>{{ $document->concept }}</div>
        @if($document->factus_cufe)
            <div><span class="muted">CUDS (DIAN)</span><br>{{ $document->factus_cufe }}</div>
        @endif
    </section>

    <section class="totals">
        <p>Subtotal <span style="float:right">${{ number_format($document->subtotal, 2) }}</span></p>
        <p>IVA <span style="float:right">${{ number_format($document->iva_total, 2) }}</span></p>
        @if($document->retention_total > 0)
            <p>Retención <span style="float:right">-${{ number_format($document->retention_total, 2) }}</span></p>
        @endif
        <p class="total">Total <span style="float:right">${{ number_format($document->total, 2) }}</span></p>
    </section>
</body>
</html>
