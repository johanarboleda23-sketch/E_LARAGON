<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura {{ $sale->invoice_number }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#1f2937;margin:32px auto;max-width:900px;padding:0 24px;font-size:14px}header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #227c70;padding-bottom:16px;margin-bottom:24px}h1{font-size:22px;margin:0;color:#192522}.muted{color:#6b7280}.meta{display:grid;grid-template-columns:1fr 1fr;gap:12px 28px;margin-bottom:24px}.meta div{border-bottom:1px solid #e5e7eb;padding-bottom:8px}table{width:100%;border-collapse:collapse}th,td{padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:left}th{background:#f3f4f6;font-size:12px;text-transform:uppercase}.numeric{text-align:right}.totals{width:280px;margin:18px 0 0 auto}.total{font-size:17px;font-weight:bold;color:#192522;border-top:2px solid #227c70;padding-top:10px}.actions{margin-bottom:20px}.actions a,.actions button{border:0;background:#227c70;color:white;padding:9px 14px;border-radius:5px;cursor:pointer;text-decoration:none;font-size:13px}@media print{body{margin:0 auto}.actions{display:none}}
    </style>
</head>
<body>
    @if(session('success'))<div style="padding:10px;background:#ecfdf5;color:#047857;margin-bottom:14px;border-radius:6px;font-weight:bold;">{{ session('success') }}</div>@endif
    <div class="actions">
        <a href="{{ route('sales.edit', $sale) }}">✏ Editar</a>
        <button onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a href="{{ route('sales.statement', $sale) }}" target="_blank">Ver estado de cartera</a>
        @if($sale->accounting_voucher_id)
            <a target="_blank" href="{{ route('accounting.vouchers.accounting', $sale->accounting_voucher_id) }}">Ver contabilización</a>
        @else
            <form action="{{ route('sales.account', $sale) }}" method="POST" style="display:inline" onsubmit="return confirm('¿Contabilizar esta factura ahora?')">@csrf<button type="submit">Contabilizar ahora</button></form>
        @endif
        <form action="{{ route('sales.email', $sale) }}" method="POST" style="display:inline-flex;gap:4px;align-items:center">
            @csrf
            <input type="email" name="email" required value="{{ $sale->customer_email }}" placeholder="correo@cliente.com" style="padding:8px;border-radius:5px;border:1px solid #cbd6cf;font-size:12px;">
            <button type="submit">✉ Enviar por correo</button>
        </form>
    </div>
    <header>
        <div>
            <p class="muted">{{ config('app.name') }}</p>
            <h1>Factura de venta</h1>
        </div>
        <strong>{{ $sale->invoice_number }}</strong>
    </header>

    <section class="meta">
        <div><span class="muted">Cliente</span><br>{{ $sale->customer_name }}</div>
        <div><span class="muted">Fecha</span><br>{{ $sale->sale_date->format('d/m/Y') }}</div>
        @if($sale->customer_document)
            <div><span class="muted">Documento</span><br>{{ $sale->customer_document }}</div>
        @endif
        @if($sale->customer_email)
            <div><span class="muted">Correo</span><br>{{ $sale->customer_email }}</div>
        @endif
        @if($sale->factus_cufe)
            <div><span class="muted">CUFE (DIAN)</span><br>{{ $sale->factus_cufe }}</div>
        @endif
    </section>

    <table>
        <thead><tr><th>Producto</th><th class="numeric">Cantidad</th><th class="numeric">Valor unitario</th><th class="numeric">Total</th></tr></thead>
        <tbody>
            @foreach($sale->details as $detail)
                <tr>
                    <td>{{ $detail->item->name }}</td>
                    <td class="numeric">{{ $detail->quantity }}</td>
                    <td class="numeric">${{ number_format($detail->unit_price, 2) }}</td>
                    <td class="numeric">${{ number_format($detail->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="totals">
        <p>Subtotal <span style="float:right">${{ number_format($sale->subtotal, 2) }}</span></p>
        @if($sale->discount_total > 0)
            <p>Descuentos <span style="float:right">-${{ number_format($sale->discount_total, 2) }}</span></p>
        @endif
        <p>IVA <span style="float:right">${{ number_format($sale->iva_total, 2) }}</span></p>
        @if($sale->retention_total > 0)
            <p>Retención <span style="float:right">-${{ number_format($sale->retention_total, 2) }}</span></p>
        @endif
        <p class="total">Total <span style="float:right">${{ number_format($sale->total, 2) }}</span></p>
    </section>
</body>
</html>
