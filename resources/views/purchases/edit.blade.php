<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar compra {{ $purchase->invoice_number }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#1f2937;margin:32px auto;max-width:640px;padding:0 24px;font-size:14px}
        h1{font-size:20px;color:#831843;margin:0 0 20px}
        label{display:block;font-weight:bold;font-size:11px;text-transform:uppercase;color:#6b7280;margin:14px 0 4px}
        input,select{width:100%;box-sizing:border-box;padding:9px;border:1px solid #cbd6cf;border-radius:8px;font-size:13px}
        .actions{margin-top:24px;display:flex;gap:10px}
        .actions a,.actions button{border:0;padding:10px 16px;border-radius:6px;font-size:13px;cursor:pointer;text-decoration:none}
        .btn-save{background:#be185d;color:white}
        .btn-cancel{background:#f3f4f6;color:#374151}
        .hint{color:#9ca3af;font-size:11px;margin-top:4px}
    </style>
</head>
<body>
    <h1>Editar factura de compra</h1>
    <p class="hint">Solo se editan los datos del encabezado. Para corregir valores, cantidades o impuestos ya contabilizados, usa una nota crédito/débito.</p>
    <form method="POST" action="{{ route('purchases.update', $purchase) }}">
        @csrf
        @method('PUT')

        <label>N° Factura Proveedor</label>
        <input type="text" name="invoice_number" value="{{ old('invoice_number', $purchase->invoice_number) }}" required>

        <label>Fecha</label>
        <input type="date" name="purchase_date" value="{{ old('purchase_date', $purchase->purchase_date->toDateString()) }}" required>

        <label>Proveedor</label>
        <input type="text" name="provider" value="{{ old('provider', $purchase->provider) }}" required>

        <label>NIT / Documento</label>
        <input type="text" name="provider_nit" value="{{ old('provider_nit', $purchase->provider_nit) }}">

        <label>Forma de pago</label>
        <select name="payment_method_id">
            <option value="">Sin forma de pago</option>
            @foreach($paymentMethods as $paymentMethod)
                <option value="{{ $paymentMethod->id }}" @selected(old('payment_method_id', $purchase->payment_method_id) == $paymentMethod->id)>{{ $paymentMethod->name }}</option>
            @endforeach
        </select>

        @if($errors->any())
            <p style="color:#b91c1c;margin-top:14px;">{{ $errors->first() }}</p>
        @endif

        <div class="actions">
            <button type="submit" class="btn-save">💾 Guardar cambios</button>
            <a href="{{ route('purchases.show', $purchase) }}" class="btn-cancel">Cancelar</a>
        </div>
    </form>
</body>
</html>
