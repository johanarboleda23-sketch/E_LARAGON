<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $certificate->subject }}</title>
    <style>
        body{margin:0;background:#f3f5f1;color:#192522;font-family:Arial,sans-serif}.toolbar{max-width:820px;margin:20px auto 0;display:flex;gap:8px}.toolbar button{border:0;border-radius:8px;background:#227c70;color:#fff;padding:10px 14px;font-weight:bold;cursor:pointer}.paper{max-width:820px;margin:12px auto 32px;background:#fff;border:1px solid #d7dfd8;border-radius:16px;overflow:hidden;box-shadow:0 12px 30px rgba(25,37,34,.08)}header{background:#192522;color:#fff;padding:42px 52px}header p{margin:0;color:#9bc9b4;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase}h1{margin:10px 0 0;font-size:28px}main{padding:48px 52px;min-height:520px}main p{white-space:pre-line;line-height:1.7}.summary{margin:30px 0;padding:20px;border:1px solid #d7dfd8;border-left:5px solid #227c70;background:#f8faf8;border-radius:10px}.summary small{display:block;color:#71807a;font-weight:bold;text-transform:uppercase}.summary strong{display:block;margin-top:5px;color:#227c70;font-size:28px}footer{padding:20px 52px;background:#f3f5f1;border-top:1px solid #d7dfd8;color:#52635b;font-size:12px}@media print{body{background:#fff}.toolbar{display:none}.paper{margin:0;border:0;box-shadow:none;border-radius:0;max-width:none}header{padding:36px 48px}main{padding:42px 48px}footer{padding:18px 48px}}
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Imprimir / guardar PDF</button></div>
    <article class="paper">
        <header>
            <p>{{ $certificate->kind === 'customer' ? 'Certificado tributario' : 'Solicitud tributaria' }}</p>
            <h1>{{ $certificate->subject }}</h1>
        </header>
        <main>
            <p><strong>{{ $certificate->third_party_name }}</strong>{{ $certificate->third_party_document ? ' · '.$certificate->third_party_document : '' }}</p>
            <p>{{ $certificate->body }}</p>
            <div class="summary"><small>Valor registrado en contabilidad · Año gravable {{ $certificate->taxable_year }}</small><strong>${{ number_format($certificate->total_amount, 2, ',', '.') }}</strong></div>
            @if ($certificate->account_breakdown)
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr><th style="padding:8px;text-align:left;border-bottom:1px solid #d7dfd8">Cuenta</th><th style="padding:8px;text-align:right;border-bottom:1px solid #d7dfd8">Valor</th></tr></thead>
                    <tbody>@foreach ($certificate->account_breakdown as $code => $account)<tr><td style="padding:8px">{{ $code }} - {{ $account['name'] }}</td><td style="padding:8px;text-align:right">${{ number_format($account['amount'], 2, ',', '.') }}</td></tr>@endforeach</tbody>
                </table>
            @endif
            <p>Cordialmente,</p>
        </main>
        <footer><strong>{{ $certificate->company?->name ?? 'Empresa expedidora' }}</strong><br>{{ $certificate->company?->tax_id ? 'NIT '.$certificate->company->tax_id : '' }}{{ $certificate->company?->phone ? ' · '.$certificate->company->phone : '' }}{{ $certificate->company?->email ? ' · '.$certificate->company->email : '' }}</footer>
    </article>
</body>
</html>
