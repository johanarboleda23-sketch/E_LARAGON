<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impresión masiva - {{ $label }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#1f2937;margin:0 auto;max-width:900px;padding:24px;font-size:14px}
        .document{page-break-after:always;border-bottom:2px solid #0f766e;padding-bottom:24px;margin-bottom:24px}
        .document:last-child{page-break-after:auto}
        h1{font-size:20px;margin:0 0 8px;color:#115e59}
        table{width:100%;border-collapse:collapse;margin-top:12px}
        th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left}
        th{background:#f3f4f6;font-size:11px;text-transform:uppercase}
        .numeric{text-align:right}
        .total{font-weight:bold;color:#115e59}
        .actions{margin-bottom:20px}
        .actions button{border:0;background:#0f766e;color:white;padding:9px 14px;border-radius:5px;cursor:pointer}
        @media print{.actions{display:none}}
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">Imprimir / Guardar como PDF</button></div>

    @foreach ($documents as $document)
        @php
            $isVoucher = $document instanceof \App\Models\AccountingVoucher;
            $consecutive = $document->consecutive ?? $document->invoice_number ?? $document->id;
            $party = $document->third_party_name ?? $document->customer_name ?? $document->provider ?? $document->third_party ?? ($document->supplier->name ?? '');
            $date = $document->document_date ?? $document->sale_date ?? $document->purchase_date ?? $document->voucher_date ?? null;
            $total = $document->total ?? $document->total_pagar ?? $document->total_debit ?? 0;
            $lines = $document->lines ?? $document->details ?? collect();
        @endphp
        <section class="document">
            <h1>{{ $label }} {{ $consecutive }}</h1>
            <p><strong>Tercero:</strong> {{ $party }} — <strong>Fecha:</strong> {{ $date?->format('d/m/Y') }}</p>

            @if ($lines->isNotEmpty())
                @if ($isVoucher)
                    <table>
                        <thead><tr><th>Cuenta</th><th>Detalle</th><th class="numeric">Débito</th><th class="numeric">Crédito</th></tr></thead>
                        <tbody>
                            @foreach ($lines as $line)
                                <tr>
                                    <td>{{ $line->account->code ?? '' }} {{ $line->account->name ?? '' }}</td>
                                    <td>{{ $line->detail }}</td>
                                    <td class="numeric">${{ number_format($line->debit, 2) }}</td>
                                    <td class="numeric">${{ number_format($line->credit, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="total">Total débito: ${{ number_format($document->total_debit, 2) }} — Total crédito: ${{ number_format($document->total_credit, 2) }}</p>
                @else
                    <table>
                        <thead><tr><th>Detalle</th><th class="numeric">Cantidad</th><th class="numeric">Total</th></tr></thead>
                        <tbody>
                            @foreach ($lines as $line)
                                <tr>
                                    <td>{{ $line->description ?? $line->item->name ?? '' }}</td>
                                    <td class="numeric">{{ $line->quantity }}</td>
                                    <td class="numeric">${{ number_format($line->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="total">Total: ${{ number_format($total, 2) }}</p>
                @endif
            @else
                <p class="total">Total: ${{ number_format($total, 2) }}</p>
            @endif
        </section>
    @endforeach
</body>
</html>
