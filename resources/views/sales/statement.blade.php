<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estado de cartera · {{ $customer }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#1f2937;margin:32px auto;max-width:900px;padding:0 24px;font-size:14px}
        header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #227c70;padding-bottom:16px;margin-bottom:24px}
        h1{font-size:20px;margin:0;color:#192522}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:left}
        th{background:#f3f4f6;font-size:12px;text-transform:uppercase}
        .numeric{text-align:right}
        .total{font-size:17px;font-weight:bold;color:#192522;border-top:2px solid #227c70;padding-top:10px;text-align:right;margin-top:14px}
        .back{border:0;background:#227c70;color:white;padding:9px 14px;border-radius:5px;text-decoration:none;font-size:13px}
        .badge{font-size:11px;font-weight:bold;padding:2px 8px;border-radius:10px}
        .badge-paid{background:#ecfdf5;color:#047857}
        .badge-partial{background:#fff8df;color:#8b6811}
        .badge-pending{background:#fef2f2;color:#b91c1c}
        .pay-form{display:grid;min-width:230px;gap:5px}
        .pay-form input,.pay-form select{box-sizing:border-box;width:100%;padding:5px;border:1px solid #d1d5db;border-radius:4px;font-size:11px}
        .pay-form button{border:0;background:#227c70;color:white;padding:7px 9px;border-radius:4px;font-size:11px;cursor:pointer}
    </style>
</head>
<body>
    @if(session('success'))<div style="padding:10px;background:#ecfdf5;color:#047857;margin-bottom:14px;border-radius:6px;font-weight:bold;">{{ session('success') }}</div>@endif
    @if($errors->any())<div style="padding:10px;background:#fef2f2;color:#b91c1c;margin-bottom:14px;border-radius:6px;font-weight:bold;">{{ $errors->first() }}</div>@endif
    <header>
        <div>
            <p style="color:#6b7280;margin:0 0 2px;">Cuentas por cobrar</p>
            <h1>{{ $customer }}</h1>
        </div>
        <a class="back" href="{{ route('sales.index') }}">⌂ Ventas</a>
    </header>

    <table>
        <thead><tr><th>Factura</th><th>Fecha</th><th class="numeric">Total</th><th class="numeric">Abonado</th><th class="numeric">Saldo</th><th>Estado</th><th>Pagos y comprobantes</th><th>Registrar pago</th></tr></thead>
        <tbody>
            @forelse($documents as $document)
                @php
                    $paid = $document->paidAmount();
                    $docBalance = $document->balanceDue((float) $document->total);
                @endphp
                <tr>
                    <td><a href="{{ route('sales.show', $document) }}" target="_blank">{{ $document->invoice_number }}</a></td>
                    <td>{{ $document->sale_date->format('d/m/Y') }}</td>
                    <td class="numeric">${{ number_format($document->total, 2) }}</td>
                    <td class="numeric">${{ number_format($paid, 2) }}</td>
                    <td class="numeric">${{ number_format($docBalance, 2) }}</td>
                    <td>
                        @if($docBalance <= 0)<span class="badge badge-paid">Pagada</span>
                        @elseif($paid > 0)<span class="badge badge-partial">Abono parcial</span>
                        @else<span class="badge badge-pending">Pendiente</span>
                        @endif
                    </td>
                    <td>
                        @foreach($document->payments as $payment)
                            <div>${{ number_format((float) $payment->amount, 2) }} · {{ $payment->payment_date->format('d/m/Y') }} · {{ $payment->paymentMethod?->name ?? $payment->method ?? 'Pago' }}
                                @if($payment->reference) <span>({{ $payment->reference }})</span> @endif
                                @if($payment->accountingVoucher)<br><a href="{{ route('accounting.vouchers.accounting', $payment->accountingVoucher) }}" target="_blank">{{ $payment->accountingVoucher->consecutive }}</a>@endif
                            </div>
                        @endforeach
                    </td>
                    <td>
                        @if($docBalance > 0)
                            <form class="pay-form" method="POST" action="{{ route('sales.payments.store', $document) }}">
                                @csrf
                                <input type="number" step="0.01" min="0.01" max="{{ $docBalance }}" name="amount" value="{{ number_format($docBalance, 2, '.', '') }}" placeholder="Valor del abono" required>
                                <input type="hidden" name="payment_date" value="{{ now()->toDateString() }}">
                                <select name="payment_method_id" required>
                                    <option value="">Cuenta de caja o banco</option>
                                    @foreach($paymentMethods as $method)
                                        <option value="{{ $method->id }}">{{ $method->name }} · {{ $method->account?->code }}</option>
                                    @endforeach
                                </select>
                                <select name="counter_account_id" required>
                                    <option value="">Cuenta de cartera</option>
                                    @foreach($counterAccounts as $account)
                                        <option value="{{ $account->id }}" @selected($account->id === $defaultCounterAccountId)>{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="reference" maxlength="100" placeholder="Referencia del banco o corresponsal">
                                <input type="text" name="notes" maxlength="255" placeholder="Observaciones (opcional)">
                                <button type="submit">Registrar pago y recibo de caja</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No hay facturas registradas para este cliente.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="total">Saldo total pendiente: ${{ number_format($balance, 2) }}</p>
</body>
</html>
