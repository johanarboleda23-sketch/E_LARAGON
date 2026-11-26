<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $certificate->subject }}</title>
</head>
<body style="margin:0;background:#f3f5f1;color:#192522;font-family:Arial,sans-serif;line-height:1.55">
    <div style="max-width:760px;margin:24px auto;background:#fff;border:1px solid #d7dfd8;border-radius:16px;overflow:hidden">
        <header style="background:#192522;color:#fff;padding:28px 32px">
            <p style="margin:0;color:#9bc9b4;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase">{{ $certificate->kind === 'customer' ? 'Certificado tributario' : 'Solicitud tributaria' }}</p>
            <h1 style="margin:8px 0 0;font-size:24px">{{ $certificate->subject }}</h1>
        </header>
        <main style="padding:32px">
            <p><strong>{{ $certificate->third_party_name }}</strong>@if ($certificate->third_party_document)<br>Documento: {{ $certificate->third_party_document }}@endif</p>
            <p style="white-space:pre-line">{{ $certificate->body }}</p>
            <div style="margin:24px 0;padding:18px;border:1px solid #d7dfd8;border-left:5px solid #227c70;background:#f8faf8;border-radius:10px">
                <p style="margin:0;color:#71807a;font-size:12px;text-transform:uppercase;font-weight:bold">Valor registrado en contabilidad</p>
                <p style="margin:4px 0 0;color:#227c70;font-size:24px;font-weight:bold">${{ number_format($certificate->total_amount, 2, ',', '.') }}</p>
                <p style="margin:6px 0 0;color:#71807a;font-size:12px">Año gravable {{ $certificate->taxable_year }}</p>
            </div>
            @if ($certificate->account_breakdown)
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr><th style="padding:8px;text-align:left;border-bottom:1px solid #d7dfd8">Cuenta</th><th style="padding:8px;text-align:right;border-bottom:1px solid #d7dfd8">Valor</th></tr></thead>
                    <tbody>@foreach ($certificate->account_breakdown as $code => $account)<tr><td style="padding:8px">{{ $code }} - {{ $account['name'] }}</td><td style="padding:8px;text-align:right">${{ number_format($account['amount'], 2, ',', '.') }}</td></tr>@endforeach</tbody>
                </table>
            @endif
            <p style="white-space:pre-line">Cordialmente,</p>
        </main>
        <footer style="padding:18px 32px;background:#f3f5f1;border-top:1px solid #d7dfd8;color:#52635b;font-size:12px">
            <strong>{{ $company?->name ?? 'Empresa expedidora' }}</strong><br>
            {{ $company?->tax_id ? 'NIT '.$company->tax_id : '' }}{{ $company?->phone ? ' · '.$company->phone : '' }}{{ $company?->email ? ' · '.$company->email : '' }}
        </footer>
    </div>
</body>
</html>
