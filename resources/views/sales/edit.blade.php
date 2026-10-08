<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar factura {{ $sale->invoice_number }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#1f2937;margin:32px auto;max-width:640px;padding:0 24px;font-size:14px}
        h1{font-size:20px;color:#192522;margin:0 0 20px}
        label{display:block;font-weight:bold;font-size:11px;text-transform:uppercase;color:#6b7280;margin:14px 0 4px}
        input{width:100%;box-sizing:border-box;padding:9px;border:1px solid #cbd6cf;border-radius:8px;font-size:13px}
        .actions{margin-top:24px;display:flex;gap:10px}
        .actions a,.actions button{border:0;padding:10px 16px;border-radius:6px;font-size:13px;cursor:pointer;text-decoration:none}
        .btn-save{background:#227c70;color:white}
        .btn-cancel{background:#f3f4f6;color:#374151}
        .hint{color:#9ca3af;font-size:11px;margin-top:4px}
    </style>
</head>
<body>
    <h1>Editar factura de venta</h1>
    <p class="hint">Solo se editan los datos del encabezado. Para corregir valores, cantidades o impuestos ya contabilizados, usa una nota crédito/débito.</p>
    <form method="POST" action="{{ route('sales.update', $sale) }}">
        @csrf
        @method('PUT')

        <label>N° Factura</label>
        <input type="text" name="invoice_number" value="{{ old('invoice_number', $sale->invoice_number) }}" required>

        <label>Fecha</label>
        <input type="date" name="sale_date" value="{{ old('sale_date', $sale->sale_date->toDateString()) }}" required>

        <label>Cliente</label>
        <input type="text" name="customer_name" value="{{ old('customer_name', $sale->customer_name) }}" required>

        <label>Documento cliente</label>
        <input type="text" name="customer_document" value="{{ old('customer_document', $sale->customer_document) }}">

        <label>Correo cliente</label>
        <input type="email" name="customer_email" value="{{ old('customer_email', $sale->customer_email) }}">

        @if($errors->any())
            <p style="color:#b91c1c;margin-top:14px;">{{ $errors->first() }}</p>
        @endif

        <div class="actions">
            <button type="submit" class="btn-save">💾 Guardar cambios</button>
            <a href="{{ route('sales.show', $sale) }}" class="btn-cancel">Cancelar</a>
        </div>
    </form>
</body>
</html>
