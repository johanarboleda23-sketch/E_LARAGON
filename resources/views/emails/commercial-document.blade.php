<p>Hola {{ $document->third_party_name }},</p>

<p>Te enviamos {{ mb_strtolower($documentLabel) }} <strong>{{ $document->consecutive }}</strong>.</p>
<p>Fecha: {{ $document->document_date->format('d/m/Y') }}</p>
@if ($document->referenceSale || $document->referencePurchase)
    <p>Factura origen: {{ $document->referenceSale?->invoice_number ?? $document->referencePurchase?->invoice_number }}</p>
@endif

<table style="width:100%;border-collapse:collapse">
    <thead>
        <tr>
            <th style="padding:8px;border-bottom:1px solid #d1d5db;text-align:left">Detalle</th>
            <th style="padding:8px;border-bottom:1px solid #d1d5db;text-align:right">Cantidad</th>
            <th style="padding:8px;border-bottom:1px solid #d1d5db;text-align:right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($document->lines as $line)
            <tr>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb">{{ $line->description }}</td>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right">{{ $line->quantity }}</td>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right">${{ number_format($line->line_total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p><strong>Total: ${{ number_format($document->total, 2) }}</strong></p>

@if ($document->notes)
    <p>{{ $document->notes }}</p>
@endif
