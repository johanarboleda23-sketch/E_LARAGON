<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar documento {{ $document->consecutive }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#1f2937;margin:32px auto;max-width:640px;padding:0 24px;font-size:14px}
        h1{font-size:20px;color:#831843;margin:0 0 20px}
        label{display:block;font-weight:bold;font-size:11px;text-transform:uppercase;color:#6b7280;margin:14px 0 4px}
        input,select{width:100%;box-sizing:border-box;padding:9px;border:1px solid #cbd6cf;border-radius:8px;font-size:13px}
        .actions{margin-top:24px;display:flex;gap:10px}
        .actions a,.actions button{border:0;padding:10px 16px;border-radius:6px;font-size:13px;cursor:pointer;text-decoration:none}
        .btn-save{background:#db2777;color:white}
        .btn-cancel{background:#f3f4f6;color:#374151}
        .hint{color:#9ca3af;font-size:11px;margin-top:4px}
    </style>
</head>
<body>
    <h1>Editar documento soporte</h1>
    <p class="hint">Al guardar, si ya estaba contabilizado se revierte el asiento anterior y se genera uno nuevo con los valores corregidos.</p>
    <form method="POST" action="{{ route('support-documents.update', $document) }}">
        @csrf
        @method('PUT')

        <label>Consecutivo</label>
        <input type="text" name="consecutive" value="{{ old('consecutive', $document->consecutive) }}" required>

        <label>Fecha</label>
        <input type="date" name="document_date" value="{{ old('document_date', $document->document_date->toDateString()) }}" required>

        <label>Proveedor</label>
        <select name="third_party_id" required>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected(old('third_party_id', $document->third_party_id) == $supplier->id)>{{ $supplier->name }}{{ $supplier->document ? ' - '.$supplier->document : '' }}</option>
            @endforeach
        </select>

        <label>Concepto</label>
        <input type="text" name="concept" value="{{ old('concept', $document->concept) }}" required>

        <label>Subtotal</label>
        <input type="number" step="0.01" min="0" name="subtotal" value="{{ old('subtotal', $document->subtotal) }}" required>

        <label>IVA</label>
        <input type="number" step="0.01" min="0" name="iva_total" value="{{ old('iva_total', $document->iva_total) }}" required>

        <label>Concepto de retención</label>
        <select name="withholding_concept" required>
            @foreach($withholdings['concepts'] as $key => $concept)
                <option value="{{ $key }}" @selected(old('withholding_concept', $document->withholding_concept) === $key)>{{ $concept['label'] }}</option>
            @endforeach
        </select>

        <label style="display:flex;align-items:center;gap:6px;flex-direction:row;text-transform:none;font-size:13px;color:#374151;">
            <input type="checkbox" name="skip_dian" value="1" style="width:auto;" @checked($document->skip_dian)> No enviar a la DIAN (uso interno)
        </label>

        @if($errors->any())
            <p style="color:#b91c1c;margin-top:14px;">{{ $errors->first() }}</p>
        @endif

        <div class="actions">
            <button type="submit" class="btn-save">💾 Guardar cambios</button>
            <a href="{{ route('support-documents.show', $document) }}" class="btn-cancel">Cancelar</a>
        </div>
    </form>
</body>
</html>
