<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cartera de proveedores (C x P)</title>
    <style>
        body{font-family:Arial,sans-serif;color:#1f2937;margin:32px auto;max-width:900px;padding:0 24px;font-size:14px}
        header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #1d4ed8;padding-bottom:16px;margin-bottom:24px}
        h1{font-size:20px;margin:0;color:#1e3a8a}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:left}
        th{background:#f3f4f6;font-size:12px;text-transform:uppercase}
        .numeric{text-align:right}
        .total{font-size:17px;font-weight:bold;color:#1e3a8a;border-top:2px solid #1d4ed8;padding-top:10px;text-align:right;margin-top:14px}
        .back{border:0;background:#1d4ed8;color:white;padding:9px 14px;border-radius:5px;text-decoration:none;font-size:13px}
        .view-link{color:#1d4ed8;font-weight:bold;text-decoration:none;font-size:12px}
    </style>
</head>
<body>
    <header>
        <div>
            <p style="color:#6b7280;margin:0 0 2px;">Cuentas por pagar · Por proveedor</p>
            <h1>Cartera de proveedores (C x P)</h1>
        </div>
        <a class="back" href="{{ route('purchases.index') }}">⌂ Compras</a>
    </header>

    <table>
        <thead><tr><th>Proveedor</th><th class="numeric">Facturas</th><th class="numeric">Saldo pendiente</th><th>Detalle</th></tr></thead>
        <tbody>
            @forelse($providers as $row)
                <tr>
                    <td>{{ $row['provider'] ?: '(Sin proveedor)' }}</td>
                    <td class="numeric">{{ $row['invoice_count'] }}</td>
                    <td class="numeric">${{ number_format($row['balance'], 2) }}</td>
                    <td><a class="view-link" href="{{ route('purchases.statement', $row['sample_id']) }}" target="_blank">Ver estado de cuenta →</a></td>
                </tr>
            @empty
                <tr><td colspan="4">No hay saldos pendientes con proveedores. ¡Cartera al día!</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="total">Total cuentas por pagar: ${{ number_format($totalBalance, 2) }}</p>
</body>
</html>
